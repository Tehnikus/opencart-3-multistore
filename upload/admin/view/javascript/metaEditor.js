document.addEventListener('DOMContentLoaded', async ()=> {
  const interface = await fetch(`index.php?route=seo/meta_editor/fetchGetInterface&user_token=${user_token}`).then(r => r.json());
  const rows      = await loadBatch('seo/meta_editor','getPages', {type: pageType}, user_token, 100, (currentCount) => {
    progressCount(document.getElementById('progressbar'), currentCount.length, pagesCount)
  }, false);
  const data = [];
  const index = {};

  for (const row of rows) {
    const id = row['column_id'];
    const langId = row['language_id'];
    
    if (!index[id]) {
      index[id] = { column_id: id, lang_data: {} };
      data.push(index[id]);
    }
    index[id].lang_data[langId] = row;
  }

  
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
    renderEditor(interface, data, tableElement);
  });
});

function renderEditor(interface, data, tableElement) {
  console.log(data)

  // Table instance
  const metaEditorTable = new nimbleTable({
    table: tableElement,
    idField:  'column_id',
    pagination: {perPage: 200},
    template: (row) => renderRow(interface, row),
    addEventListeners: (table) => {

    },
    onFilterEnd: (filteredMap) => {
    },
    onRowDelete: async (row) => {
    }
  });


  // Render table header element 
  const tableHeaderElement = renderHeader(interface);
  // Append header to table, 
  metaEditorTable.renderHeader(tableHeaderElement);
  metaEditorTable.setData(data);


function renderHeader(interface) {
  const thead = document.createElement('thead');

  // Create filter selects
  const languageSelect  = interface.languageSelect.cloneNode(true);
  const storeSelect     = interface.storeSelect.cloneNode(true);
  // Add empty values to filter selects
  languageSelect.add(Object.assign(document.createElement('option'), {value: '', textContent: interface.lang.column_language}), languageSelect.options[0]);
  storeSelect.add(Object.assign(document.createElement('option'), {value: '', textContent: interface.lang.column_store}), storeSelect.options[0]);

  // Set dataset
  languageSelect.dataset.searchColumn = 'language_id';
  storeSelect.dataset.searchColumn    = 'store_id';

  // Row type select options 
  
  thead.innerHTML = `
    <tr>
      <th style="width: 67%" class="text-center">
        <div class="input-group flex">
          <input type="text" class="form-control" data-search-column="lang_data.*.meta_title" placeholder="${interface.lang.input_search} ${interface.lang.input_meta_title}">
          <input type="text" class="form-control" data-search-column="lang_data.*.h1" placeholder="${interface.lang.input_search} ${interface.lang.input_h1}">
        </div>
        <div class="input-group flex">
          <input type="text" class="form-control" data-search-column="lang_data.*.meta_description" placeholder="${interface.lang.input_search} ${interface.lang.input_meta_description}">
        </div>
      </th>
      <th>
        <div class="input-group flex">
          <select class="form-control" data-search-column="lang_data.*.description">
            <option style="background-color: #ffffff; border-color: #cccccc; color: #555555;" value="">${interface.lang.input_description}</option>
            <option style="background-color: #cbeacb; border-color: #b9e2b9; color: #398c39;" value="">${interface.lang.input_description}</option>
            <option style="background-color: #f5c1bb; border-color: #f3b5ad; color: #c72f1d;" value="">${interface.lang.input_description}</option>
          </select>
          <select class="form-control" data-search-column="lang_data.*.seo_description">
            <option style="background-color: #ffffff; border-color: #cccccc; color: #555555;" value="">${interface.lang.input_seo_description}</option>
            <option style="background-color: #cbeacb; border-color: #b9e2b9; color: #398c39;" value=">2000">${interface.lang.input_seo_description} >2000</option>
            <option style="background-color: #f5c1bb; border-color: #f3b5ad; color: #c72f1d;" value=">1000">${interface.lang.input_seo_description} >1000</option>
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
      <th>
        <button type="button" class="btn btn-warning" title="${interface.lang.button_save_all}"><i class="fa fa-save"></i></button>
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
          <img src="language/${interface.languages[langId].code}/${interface.languages[langId].code}.png" loading="lazy" />
        </div>
        <div class="langSeoData">
          <div class="seoInputs">
            <div class="input-group flex">
              <span class="input-group-addon">${interface.lang.input_h1}</span>
              <input data-column="lang_data.${langId}.h1" value="${langRow.h1}" class="form-control" placeholder="${interface.lang.input_h1}">
              <span class="input-group-addon">${interface.lang.input_meta_title}</span>
              <input data-column="lang_data.${langId}.meta_title" value="${langRow.meta_title}" class="form-control" placeholder="${interface.lang.input_meta_title}">
            </div>
            <div class="input-group flex">
              <span class="input-group-addon">${interface.lang.input_meta_description_short}</span>
              <input data-column="lang_data.${langId}.meta_description" value="${langRow.meta_description}" class="form-control" placeholder="${interface.lang.input_meta_description}">
            </div>
          </div>
        </div>
        <div class="seoBadges">
          <span class="badge badge-sm strong ${langRow.description     ? 'alert-success' : 'alert-danger'}">${interface.lang.input_description}: ${langRow.description}</span>
          <span class="badge badge-sm strong ${langRow.seo_description ? 'alert-success' : 'alert-danger'}">${interface.lang.input_seo_description}: ${langRow.seo_description}</span>
          <span class="badge badge-sm strong ${langRow.footer          ? 'alert-success' : 'alert-danger'}">${interface.lang.input_footer}: ${langRow.footer}</span>
          <span class="badge badge-sm strong ${langRow.seo_keywords    ? 'alert-success' : 'alert-danger'}">${interface.lang.input_seo_keywords}: ${langRow.seo_keywords}</span>
          <span class="badge badge-sm strong ${langRow.faq             ? 'alert-success' : 'alert-danger'}">${interface.lang.input_faq}: ${langRow.faq}</span>
          <span class="badge badge-sm strong ${langRow.how_to          ? 'alert-success' : 'alert-danger'}">${interface.lang.input_how_to}: ${langRow.how_to}</span>
        </div>
      </div>
    `;
  }

  tr.innerHTML = `
    <td colspan=2>
      <div class="name text-center">
        <span class="h4">${row.lang_data[interface.defaultLanguageId].name}</span>
      </div>
      ${langRowHtml}
    </td>
    <td>
      <button type="button" class="btn btn-success" title="${interface.lang.button_save}"><i class="fa fa-save"></i></button>
    </td>
  `;

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
 * Dynamic elements event listeners
 */
function addAsyncListeners(metaEditorTable, data) {
  // Clicks
  document.addEventListener('click', e => {
    // Generate meta buttons
    if (e.target.closest('.generateMeta')) {
      generateMeta2(e.target, metaEditorTable);
    }

    // Clear filters button
    if (e.target.closest('.clearFilters')) {
      metaEditorTable.resetFilter();
      metaEditorTable.setData(data); // Set data in case language filter is used because language filter removes data
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
      console.log('savePage')
      const pageData = [];
      saveBatch('seo/meta_editor', 'savePages', pageData, user_token, batchsize = 1, callback = null, debug = false);
    }

    // Save all pages 
    if (e.target.closest('.saveAllPages')) {
      console.log('savePage')
      const pagesData = [];
      saveBatch('seo/meta_editor', 'savePages', pagesData, user_token, batchsize = 1, callback = null, debug = false);
    }
  });

  // Input changes
  document.addEventListener('change', e => {
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

    // Language filter. Removes langauge data from each row in data except selected. Then sets new data to nimbleTable
    if (e.target.closest('.languageFilter')) {
      // const newData = data.map(row => row.lang_data)
    }
  });

  // Submits
  document.addEventListener('submit', e => {
    if (e.target.closest('.fetchSaveForm')) {
      e.preventDefault();
      fetchSave(e.target);
    }
  })
}