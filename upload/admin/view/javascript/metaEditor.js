document.addEventListener('DOMContentLoaded', async ()=> {
  const interface = await fetch(`index.php?route=seo/meta_editor/fetchGetInterface&user_token=${userToken}`).then(r => r.json());
  const data      = await loadBatch('seo/meta_editor','getPages', {type: pageType}, userToken, 100, (currentCount) => {
    progressCount(document.getElementById('progressbar'), currentCount.length, pagesCount, interface.lang.message_loaded)
  }, false);
  
  // Pass static variables provided by PHP to interface that is needed for nimbleTable
  interface.variables = {pageType, columnId};

  // Language select prerender
  interface.languageSelect = renderSelect([
    ...Object.values(interface.languages).map(l => ({
        value: l.language_id,
        label: l.name
      })
    )
  ]);

  // Store select prerender
  interface.storeSelect = renderSelect([
    ...Object.values(interface.stores).map(l => ({
        value: l.store_id,
        label: l.name
      })
    )
  ]);

  // Render editor table
  const tableElements = document.querySelectorAll('.metaEditorTable');
  tableElements.forEach(tableElement => {
    // Call nimbleTable with params and add event listeners
    const metaEditorInstace = renderEditor(interface, data, tableElement);
    addAsyncListeners(metaEditorInstace, data, interface);
  });
});

function renderEditor(interface, data, tableElement) {

  // Table instance
  const metaEditorTable = new nimbleTable({
    table: tableElement,
    idField:  'column_id',
    pagination: {perPage: 200},
    template: (row) => renderRow(interface, row),
    addEventListeners: (table) => {},
    onFilterEnd: (filteredMap) => {},
  });

  // Set each loaded row type to 'existing'. Originally row type is not stored in DB, it's just for filtering purpose 
  data.forEach(row => {
    row.rowType = 'existing';
  });

  // Render table header element 
  const tableHeaderElement = renderHeader(interface);
  // Append header to table, 
  metaEditorTable.renderHeader(tableHeaderElement);
  metaEditorTable.setData(data);

  return metaEditorTable;
}

function generateMeta(button, metaEditorTable, interface) {
  const formulaRow = button.closest('tr');
  const targetField     = formulaRow.querySelector('[data-name="target_field"]').value;
  const targetLang      = formulaRow.querySelector('[data-name="language_id"]').value;
  const targetCurrency  = formulaRow.querySelector('[data-name="currency_id"]').value;
  const formula         = formulaRow.querySelector('[data-name="formula"]').value;
  const filteredRows    = {};

  let selectsValues = {};
  let selects = formulaRow.querySelectorAll('select');
  selects.forEach(select => {
    selectsValues[select.dataset.name] = select.value;
  });

  metaEditorTable.filteredOrder.forEach(id => {
    const row = metaEditorTable.getRow(id);
    filteredRows[id] = row;
  });



  // console.log(filteredRows);
  
  // console.log(row, targetField, targetLang, targetCurrency, filteredRows);
  for (const rowId in filteredRows) {
    const data = filteredRows[rowId];
    if (!data.selected) {continue}

    let generateVars = {
      name:          data.lang_data[selectsValues.language_id].name,
      price:         data.vars.price,
      minPrice:      data.vars.minPrice,
      maxPrice:      data.vars.maxPrice,
      discount:      data.vars.discount,
      rating:        data.vars.rating,
      reviews:       data.vars.reviews,
      offers:        data.vars.offers,
      parent:        data.vars.parent,
      manufacturer:  data.vars.manufacturer,
      store:         interface.currentStore.name,
    }

    const newData = structuredClone(data);
    // const result = applyFormula(formula, generateVars);


    newData.lang_data[targetLang][targetField] = applyFormula(formula, generateVars);
    newData.rowType = "updated";

    const rowElement = metaEditorTable.getRowElement(rowId);

    console.log(rowElement)

    metaEditorTable.updateRow(rowId, newData, true);
    
    // Highlight changed inputs 
    rowElement.querySelectorAll(`[data-column="lang_data.${targetLang}.${targetField}"]`).forEach(input => {
      console.log(input)
      // Set input class
      input.classList.add("alert-success");
    });
  }
}

function applyFormula(template, data) {
  const FILTERS = ['upper', 'lower', 'capitalize', 'number', 'currency'];
  const result = {
    text: '',
    errors: []
  };

  return template.replace(/{{(.*?)}}/g, (_, content) => {

    // Parse tokens inside curly braces. 
    // If token is inside quotes, it is considered as literal, left unchanged
    // If token follows | (vertical bar), it is considered as filter
    const tokens      = [];
    let current       = '';
    let insideQuotes  = false;
    let quoteChar     = '';

    for (const char of content.trim()) {
      // Parse literals inside quotes
      if (!insideQuotes && (char === '"' || char === "'")) {
        insideQuotes = true;
        quoteChar = char;
        current  += char;
      } else if (insideQuotes && char === quoteChar) {
        insideQuotes = false;
        current     += char;
        // Find filters after vertical bar
      } else if (!insideQuotes && char === '|') {
        if (current.trim()) tokens.push(current.trim());
        current = '';
      } else {
        current += char;
      }
    }

    // Push tokens to array if found
    if (current.trim()) {tokens.push(current.trim())}

    // Separate FILTERS from actual data tokens from page data
    let filter = null;
    let prefix = '';
    let suffix = '';
    const valueTokens = [];

    for (const token of tokens) {
      const lower = token.toLowerCase();
      if (FILTERS.includes(lower)) {
        filter = lower;
        continue;
      }
      const prefixMatch = token.match(/^prefix:["'](.*)["']$/);
      const suffixMatch = token.match(/^suffix:["'](.*)["']$/);
      if (prefixMatch) { prefix = prefixMatch[1]; continue; }
      if (suffixMatch) { suffix = suffixMatch[1]; continue; }
      valueTokens.push(token);
    }

    // Ищем первое непустое ненулевое значение
    let value = '';
    let isLiteral = false;

    for (const token of valueTokens) {
      // Литерал в кавычках
      if ((token.startsWith('"') && token.endsWith('"')) ||
          (token.startsWith("'") && token.endsWith("'"))) {
        value = token.slice(1, -1);
        isLiteral = true;
        break;
      }
      const dataValue = data[token];
      if (dataValue !== undefined && dataValue !== null && dataValue !== '' && dataValue !== 0) {
        value = dataValue;
        break;
      }
    }

    // Check if page's data value is not empty or null or zero
    // Zero is included here to indicate zero values errors because it doesn't add any sence to meta data:
    // e.g. price = 0, or offer count = 0, or reviews = 0, etc.
    if (value === '' || value === null || value === undefined || value === 0) {
      // Accumulate errors

      return '';
    }

    // Apply filters
    if (filter) {
      switch (filter) {
        case 'upper':      value = String(value).toUpperCase(); break;
        case 'lower':      value = String(value).toLowerCase(); break;
        case 'capitalize': value = String(value).charAt(0).toUpperCase() + String(value).slice(1); break;
        case 'number':     value = Number(value).toLocaleString(); break;
        case 'currency':   value = Number(value).toLocaleString('uk-UA', { style: 'currency', currency: 'UAH' }); break;
      }
    }

    // Префикс и суффикс только для значений из data
    if (!isLiteral) {
      value = prefix + value + suffix;
    }

    return value;
  });
}


function renderHeader(interface) {
  const thead = document.createElement('thead');

  // Create language select
  const languageSelect  = interface.languageSelect.cloneNode(true);
  // Add empty values to filter select
  languageSelect.add(Object.assign(document.createElement('option'), {value: '', textContent: interface.lang.option_language}), languageSelect.options[0]);
  languageSelect.classList.add('languageSelect');
  
  thead.innerHTML = `
    <tr>
      <th style="width: 40px; position: relative">
        <label class="labelAll">
          <input type="checkbox" style="all: revert" class="selectAllRows" />
        </label>
      </th>
      <th style="width: 250px">
        ${languageSelect.outerHTML}
        <div class="input-group flex">
          <select class="form-control" data-search-column="selected">
            <option value="">${interface.lang.option_selection}</option>
            <option value="1">${interface.lang.option_selection_selected}</option>
            <option value="!1">${interface.lang.option_selection_not_selected}</option>
          </select>
          <select class="form-control" data-search-column="rowType">
            <option value="">${interface.lang.option_state}</option>
            <option style="background-color: #d9edf7; border-color: #bce8f1; color: #214c61;" value="updated">${interface.lang.option_state_updated}</option>
            <option style="background-color: #cbeacb; border-color: #b9e2b9; color: #398c39;" value="saved">${interface.lang.option_state_saved}</option>
            <option style="background-color: #f5c1bb; border-color: #f3b5ad; color: #c72f1d;" value="hasError">${interface.lang.option_state_hasError}</option>
          </select>
        </div>
      </th>
      <th>
        <div class="input-group flex">
          <input type="text" class="form-control" data-search-column="lang_data.*.meta_title" placeholder="${interface.lang.input_search} ${interface.lang.input_meta_title}">
          <input type="text" class="form-control" data-search-column="lang_data.*.h1" placeholder="${interface.lang.input_search} ${interface.lang.input_h1}">
        </div>
        <input type="text" class="form-control" data-search-column="lang_data.*.meta_description" placeholder="${interface.lang.input_search} ${interface.lang.input_meta_description}">
      </th>
      <th style="width: 430px">
        <div class="input-group flex">
          <select class="form-control" data-search-column="lang_data.*.description">
            <option style="background-color: #ffffff; border-color: #cccccc; color: #555555;" value="">${interface.lang.input_description}</option>
            <option style="background-color: #cbeacb; border-color: #b9e2b9; color: #398c39;" value=">2000">${interface.lang.input_description} >2000</option>
            <option style="background-color: #fce7c8; border-color: #f9d5a2; color: #e0890e;" value=">1000, <2000">${interface.lang.input_description} <2000</option>
            <option style="background-color: #f5c1bb; border-color: #f3b5ad; color: #c72f1d;" value="<1000">${interface.lang.input_description} <1000</option>
          </select>
          <select class="form-control" data-search-column="lang_data.*.seo_description">
            <option style="background-color: #ffffff; border-color: #cccccc; color: #555555;" value="">${interface.lang.input_seo_description}</option>
            <option style="background-color: #cbeacb; border-color: #b9e2b9; color: #398c39;" value=">2000">${interface.lang.input_seo_description} >2000</option>
            <option style="background-color: #fce7c8; border-color: #f9d5a2; color: #e0890e;" value=">1000, <2000">${interface.lang.input_seo_description} <2000</option>
            <option style="background-color: #f5c1bb; border-color: #f3b5ad; color: #c72f1d;" value="<1000">${interface.lang.input_seo_description} <1000</option>
          </select>
          <select class="form-control" data-search-column="lang_data.*.footer">
            <option style="background-color: #ffffff; border-color: #cccccc; color: #555555;" value="">${interface.lang.input_footer}</option>
            <option style="background-color: #cbeacb; border-color: #b9e2b9; color: #398c39;" value="true">${interface.lang.input_footer}</option>
            <option style="background-color: #f5c1bb; border-color: #f3b5ad; color: #c72f1d;" value="false">${interface.lang.input_footer}</option>
          </select>
        </div>
        <div class="input-group flex">
          <select class="form-control" data-search-column="lang_data.*.seo_keywords">
            <option style="background-color: #ffffff; border-color: #cccccc; color: #555555;" value="">${interface.lang.input_seo_keywords}</option>
            <option style="background-color: #cbeacb; border-color: #b9e2b9; color: #398c39;" value=">0">${interface.lang.input_seo_keywords}</option>
            <option style="background-color: #f5c1bb; border-color: #f3b5ad; color: #c72f1d;" value="<1">${interface.lang.input_seo_keywords}</option>
          </select>
          <select class="form-control" data-search-column="lang_data.*.faq">
            <option style="background-color: #ffffff; border-color: #cccccc; color: #555555;" value="">${interface.lang.input_faq}</option>
            <option style="background-color: #cbeacb; border-color: #b9e2b9; color: #398c39;" value="true">${interface.lang.input_faq}</option>
            <option style="background-color: #f5c1bb; border-color: #f3b5ad; color: #c72f1d;" value="false">${interface.lang.input_faq}</option>
          </select>
          <select class="form-control" data-search-column="lang_data.*.how_to">
            <option style="background-color: #ffffff; border-color: #cccccc; color: #555555;" value="">${interface.lang.input_how_to}</option>
            <option style="background-color: #cbeacb; border-color: #b9e2b9; color: #398c39;" value="true">${interface.lang.input_how_to}</option>
            <option style="background-color: #f5c1bb; border-color: #f3b5ad; color: #c72f1d;" value="false">${interface.lang.input_how_to}</option>
          </select>
        </div>
      </th>
      <th style="width: 40px">
        <button type="button" class="clearFilters btn btn-default" title="${interface.lang.button_clear_filters}"><i class="fa fa-times"></i></button>
      </th>
      <th style="width: 60px; text-align: center;">
        <button type="button" class="undoAllPages btn btn-warning" title="${interface.lang.button_undo}"><i class="fa fa-undo"></i></button>
        <button type="button" class="saveAllPages btn btn-success" title="${interface.lang.button_save_all}"><i class="fa fa-save"></i></button>
      </th>
    </tr>
  `;

  return thead;
}

function renderRow(interface, row) {
  const tr = document.createElement('tr');
  tr.dataset.id = row.column_id || '';

  let langRowHtml = '';
  for (const langId in row.lang_data) {
    const langRow = row.lang_data[langId];
    langRowHtml += `
      <div class="langData">
        <div class="langFlag">
          <img width="16" height="11" src="language/${interface.languages[langId].code}/${interface.languages[langId].code}.png" loading="lazy" />
        </div>
        <div class="langSeoData">
          <div class="seoInputs">
            <div class="input-group flex">
              <span data-addon="lang_data.${langId}.h1" class="input-group-addon">${interface.lang.input_h1}:${langRow.h1.length}</span>
              <input data-column="lang_data.${langId}.h1" value="${langRow.h1}" class="form-control" placeholder="${interface.lang.input_h1}">
              <span data-addon="lang_data.${langId}.meta_title" class="input-group-addon">${interface.lang.input_meta_title}:${langRow.meta_title.length}</span>
              <input data-column="lang_data.${langId}.meta_title" value="${langRow.meta_title}" class="form-control" placeholder="${interface.lang.input_meta_title}">
            </div>
            <div class="input-group flex">
              <span data-addon="lang_data.${langId}.meta_description" class="input-group-addon">${interface.lang.input_meta_description_short}:${langRow.meta_description.length}</span>
              <input data-column="lang_data.${langId}.meta_description" value="${langRow.meta_description}" class="form-control" placeholder="${interface.lang.input_meta_description}">
            </div>
          </div>
        </div>
        <div class="seoBadges">
          <span class="badge badge-sm strong ${langRow.description     ? 'alert-success' : 'alert-danger'}">${interface.lang.input_description}: ${langRow.description}</span>
          <span class="badge badge-sm strong ${langRow.seo_description ? 'alert-success' : 'alert-danger'}">${interface.lang.input_seo_description}: ${langRow.seo_description}</span>
          <span class="badge badge-sm strong ${langRow.seo_keywords    ? 'alert-success' : 'alert-danger'}">${interface.lang.input_seo_keywords}: ${langRow.seo_keywords}</span>
          <span class="badge badge-sm strong ${langRow.footer          ? 'alert-success' : 'alert-danger'}">${interface.lang.input_footer}: ${langRow.footer ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>'}</span>
          <span class="badge badge-sm strong ${langRow.faq             ? 'alert-success' : 'alert-danger'}">${interface.lang.input_faq}: ${langRow.faq ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>'}</span>
          <span class="badge badge-sm strong ${langRow.how_to          ? 'alert-success' : 'alert-danger'}">${interface.lang.input_how_to}: ${langRow.how_to ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>'}</span>
        </div>
      </div>
    `;
  }

  tr.innerHTML = `
    <td class="checkRow">
      <label class="labelAll">
        <input type="checkbox" style="all: revert" data-column="selected" value="1" ${row.selected ? "checked" : ""} />
      </label>
    </td>
    <td colspan="3">
      <div class="name text-center">
        <span class="h3 strong">${row.default_name}</span>
      </div>
      ${langRowHtml}
    </td>
    <td>
      <a class="btn btn-primary"><i class="fa fa-pencil"></i></a>
    </td>
    <td style="text-align: center">
      <button type="button" class="undoPage btn btn-warning" title="${interface.lang.button_undo}"><i class="fa fa-undo"></i></button>
      <button type="button" class="savePage btn btn-success" title="${interface.lang.button_save}"><i class="fa fa-save"></i></button>
    </td>
  `;

  if (row.rowType) {
    tr.classList.add(row.rowType);
  }

  return tr;
}

// Render select from options list
function renderSelect(options, datasetAttr) {

  const select = document.createElement('select');
  select.className = 'form-control';

  if (datasetAttr) {
    Object.entries(datasetAttr).forEach(([k, v]) => {
      select.dataset[k] = v;
    });
  }

  options.forEach(opt => {
    const option = document.createElement('option');
    option.value = opt.value;
    option.textContent = opt.label;
    select.appendChild(option);
  });

  return select;
}

/**
 * Delete formula row and asynchnously save new formulas list to DB
 * @param {Element} button 
 */
function deleteFormula(button) {
  const tr = button.closest('tr');
  form = button.closest('form');
  if (tr.parentElement.querySelectorAll("[data-row-index]").length === 1) {
    tr.querySelectorAll('input').forEach(e => {e.value = ''});
    tr.querySelectorAll('select').forEach(e => {e.selectedIndex = 0});
  } else {
    button.closest('tr').remove();
  }
  fetchSave(form);
}

/**
 * Add formula row. Claer input values before adding. Increment dataset row index and row index in input names
 * @param {Element} button 
 */
function addFormula(button) {
  const row = button.closest('table').querySelector('tbody').lastElementChild;
  let rowIndex = row.dataset.rowIndex;
  let newIndex = parseInt(rowIndex) + 1;
  const newRow = row.cloneNode(true);
  newRow.dataset.rowIndex = newIndex;
  newRow.querySelectorAll('input, select').forEach(e => {
    e.name = e.name.replace(`[${rowIndex}]`, `[${newIndex}]`);
    switch (e.tagName) {
      case 'SELECT':
        e.selectedIndex = 0;
      break;
      case 'INPUT':
        if (e.type === 'checkbox' || e.type === 'radio') {
          e.checked = false;
        } else {
          e.value = '';
        }
      break;
    }
  });
  row.after(newRow);
}

/**
 * Save forms asynchronously
 * @param {Element} formElement form element to be saved. Must have action attribute
 * @param {Boolean} debug Flag to log to console
 * @returns 
 */
function fetchSave(formElement, debug = false) {
  const post_action = formElement.action;
  const formData = new FormData(formElement);
  if (debug) {
    console.log('Send to:', post_action);
    console.log('Data:', console.table([...formData.entries()]));
  }
  return fetch(post_action, {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    
    const savedInputs = formElement.querySelectorAll('input, select, textarea');
    savedInputs.forEach(input => input.classList.add(data.success ? 'success' : 'error'));

    if (data.message) {
      const messageDiv = formElement.querySelector('.message');
      if (messageDiv) {
        messageDiv.innerHTML = `<div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>${data.message}</div>`;
      }
    }

    return data; // necessary for sequential form saving
  })
  .catch(error => {
    console.error('Error while sending data:', error);
  });
}

/**
 * Save a list of forms one by one
 * @param {HTMLCollection} forms 
 */
async function saveAllForms(forms) {
  for (const form of forms) {
    await fetchSave(form);  // await every form save
  }
}

/**
 * Dynamic elements event listeners
 */
async function addAsyncListeners(metaEditorTable, data, interface) {

  const backupData = structuredClone(data);

  // Clicks
  document.addEventListener('click', async e => {
    // Generate meta buttons
    if (e.target.closest('.generateMeta')) {
      generateMeta(e.target, metaEditorTable, interface);
    }

    // Clear filters button
    if (e.target.closest('.clearFilters')) {
      metaEditorTable.resetFilter();
      metaEditorTable.setData(data); // Set data in case language filter is used because language filter removes data
      e.target.closest('thead').querySelector('.languageSelect').selectedIndex = 0;
    }

    // Add formula button
    if (e.target.closest('.addFormula')) {
      addFormula(e.target);
    }

    // Delete formula button
    if (e.target.closest('.deleteFormula')) {
      deleteFormula(e.target);
    }

    // Save single page 
    if (e.target.closest('.savePage')) {
      const newData = [];
      const rowElement = e.target.closest('tr');
      // Get row id
      const pageId = rowElement.dataset.id;
      // Get row data
      const rowData = metaEditorTable.getRow(pageId);

      // Compile POST data to flat array of objects
      for (const langId in rowData.lang_data) {        
        const langRow = rowData.lang_data[langId];        
        newData.push({
          meta_title: langRow.meta_title,
          h1: langRow.h1,
          meta_description: langRow.meta_description,
          [columnId]: langRow[columnId],
          language_id: langRow.language_id,
        });
      }

      // Get unique languages. Required for progress bar to show correct numbers because data is sent by language, not by page
      const uniqueLanguages = [...new Set(newData.map(row => row.language_id))];

      // Save batch
      const response = await saveBatch(
        model     = 'seo/meta_editor', 
        method    = 'savePages', 
        data      = newData, 
        userToken, 
        batchsize = 1, 
        callback  = (currentCount) => {progressCount(document.getElementById('progressbar'), (currentCount / uniqueLanguages.length), (newData.length / uniqueLanguages.length), interface.lang.message_saved)}, 
        debug     = false, 
        args      = pageType
      );

      rowElement.classList = "";
      rowData.rowType = "";
      
      if (response !== 0) {
        rowData.rowType = "saved";
        rowElement.classList.add("saved");
      } else {
        rowData.rowType = "hasError";
        rowElement.classList.add("hasError");
      }

    }

    // Save all pages
    if (e.target.closest('.saveAllPages')) {
      const newData = []; // New data to be posted
      const filteredRowsData = []; // Filtered rows data to set rowType for each row

      // Get only filtered rows
      metaEditorTable.filteredOrder.forEach(pageId => {
        // Get row data
        const rowData = metaEditorTable.getRow(pageId);
        // Skip rows, if not selected
        if (!rowData.selected) {return}
        // Save references to update rowType
        filteredRowsData.push(rowData);
        // Compile POST data to flat array of objects
        for (const langId in rowData.lang_data) {        
          const langRow = rowData.lang_data[langId];        
          newData.push({
            meta_title: langRow.meta_title,
            h1: langRow.h1,
            meta_description: langRow.meta_description,
            [columnId]: langRow[columnId],
            language_id: langRow.language_id,
          });
        }
      });

      // Get unique languages. Required for progress bar to show correct numbers because data is sent by language, not by page
      const uniqueLanguages = [...new Set(newData.map(row => row.language_id))];

      // Save batch
      const response = await saveBatch(
        model     = 'seo/meta_editor', 
        method    = 'savePages', 
        data      = newData, 
        userToken, 
        batchsize = 100, 
        callback  = (currentCount) => {progressCount(document.getElementById('progressbar'), (currentCount / uniqueLanguages.length), (newData.length / uniqueLanguages.length), interface.lang.message_saved)}, 
        debug     = false, 
        args      = pageType
      );

      filteredRowsData.forEach(rowData => {
        rowData.rowType = "";
      });
      
      if (response !== 0) {
        filteredRowsData.forEach(rowData => {
          // Set rowType
          rowData.rowType = "saved";
          // Update rows and their elements
          const tr = metaEditorTable.updateRow(rowData.column_id, rowData, true);
        });
      } else {
        filteredRowsData.forEach(rowData => {
          // Set rowType
          rowData.rowType = "hasError";
          // Update rows and their elements
          metaEditorTable.updateRow(rowData.column_id, rowData, true);
        });
      }
    }

    // Undo row
    if (e.target.closest('.undoPage')) {
      undoRow(e.target.closest('tr').dataset.id, backupData, metaEditorTable);
    }

    // Undo all rows
    if (e.target.closest('.undoAllPages')) {
      metaEditorTable.filteredOrder.forEach(id => {
        undoRow(id, backupData, metaEditorTable);
      })
    }
  });

  // Input changes
  document.addEventListener('input', e => {
    // Select all filtered rows
    if (e.target.closest('.selectAllRows')) {
      metaEditorTable.table.querySelectorAll('[data-column="selected"]').forEach(checkbox => {
        checkbox.checked = e.target.checked;
      });

      // Set row data selected value, so the rows out of visibility (e.g. out of current pagination page) are also checked
      metaEditorTable.filteredOrder.forEach(rowId => {
        const rowData = metaEditorTable.getRow(rowId);
        if (e.target.checked) {
          rowData.selected = 1;
        } else {
          rowData.selected = null;
        }
      });
    }

    // Set row type to "updated" on input change
    if (e.target.closest('[data-id]')) {
      // Skip row select checkbox
      if (e.target.dataset.column === "selected") {return}
      // Else update row data and appearance
      const row = e.target.closest('[data-id]') // Get row element. The element is always present pecause you can't input in invisible element
      const rowId = row.dataset.id; // Get row id
      const rowData = metaEditorTable.getRow(rowId);
      rowData.rowType = "updated"
      metaEditorTable.updateRow(rowId, rowData, false);
      row.classList = "updated";

      const inputAddon = row.querySelector(`[data-addon="${e.target.dataset.column}"]`);
      inputAddon.innerText = inputAddon.textContent.replace(/:.*/, `:${e.target.value.length}`);
    }

    // Language filter. Removes langauge data from each row in data except selected. Then sets new data to nimbleTable
    if (e.target.closest('.languageSelect')) {
      langId = e.target.value;
      const newData = filterByLangId(data, langId)
      metaEditorTable.setData(newData, keepFilters = true);
    }
  });

  // Submits
  document.addEventListener('submit', e => {
    if (e.target.closest('.fetchSaveForm')) {
      e.preventDefault();
      fetchSave(e.target);
    }
  });

  /**
   * Filter data and leave only selected language
   * @param {Array} data Array of objects with original data
   * @param {String} langId Language id or empty string
   * @returns {Array} of objects with filtered out languages except lang_data[langId]
   */
  function filterByLangId(data, langId) {
    const newData = data.map(row => {
      const newRow = { ...row }; // shallow copy of original row
      if (langId !== '') {
        newRow.lang_data = row.lang_data[langId] ? {[langId]: row.lang_data[langId]} : {}; // Filter language by id
      } else {
        newRow.lang_data = {...row.lang_data}; // Return all languages
      }
      return newRow;
    });

    return newData;
  }

  /**
   * Undo changes in single row
   * @param {Number} rowId Id of the row to be undone
   * @param {Array} data The array of original data
   */
  function undoRow(rowId, data, metaEditorTable) {    
    // Filter by language so filtered out lang data is not affected by undo
    const prevData = filterByLangId(data.filter(row => Number(row.column_id) === Number(rowId)), metaEditorTable.table.querySelector('.languageSelect').value); 
    prevData.forEach(row => {
      row.rowType = 'existing';
      metaEditorTable.updateRow(rowId, row, true);
    });
  }
}