

/**
 * Create SEO URL string in latin from non-latin string
 * @param {String} str Any string to be converted to URL slug
 * @param {String} lang Optional lang code (e.g. 'en' or 'uk') for some separate replacement rules
 * @returns {String} An URL slug with non-latin characters replaced by latin counterparts and spaces replaced by dashes
 */
function createSeoUrl(str, lang = 'en') {
  const delimiter = '-';

  abc = {' ':'-','ß':'ss','à':'a','á':'a','â':'a','ã':'a','ä':'a','å':'a','æ':'ae','ç':'c','è':'e','é':'e','ê':'e','ë':'e','ì':'i','í':'i','î':'i','ï':'yi','ð':'d','ñ':'n','ò':'o','ó':'o','ô':'o','õ':'o','ö':'o','ő':'o','ø':'o','ù':'u','ú':'u','û':'u','ü':'u','ű':'u','ý':'y','þ':'th','ÿ':'y','α':'a','β':'b','γ':'g','δ':'d','ε':'e','ζ':'z','η':'h','θ':'8','ι':'i','κ':'k','λ':'l','μ':'m','ν':'n','ξ':'3','ο':'o','π':'p','ρ':'r','σ':'s','τ':'t','υ':'y','φ':'f','χ':'x','ψ':'ps','ω':'w','ά':'a','έ':'e','ί':'i','ό':'o','ύ':'y','ή':'h','ώ':'w','ς':'s','ϊ':'i','ΰ':'y','ϋ':'y','ΐ':'i','ş':'s','ı':'i','ç':'c','ü':'u','ö':'o','ğ':'g','а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'yo','ж':'zh','з':'z','и':'i','й':'j','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'h','ц':'c','ч':'ch','ш':'sh','щ':'sh','ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya','є':'ye','і':'i','ї':'yi','ґ':'g','č':'c','ď':'d','ě':'e','ň':'n','ř':'r','š':'s','ť':'t','ů':'u','ž':'z','ą':'a','ć':'c','ę':'e','ł':'l','ń':'n','ó':'o','ś':'s','ź':'z','ż':'z','ā':'a','č':'c','ē':'e','ģ':'g','ī':'i','ķ':'k','ļ':'l','ņ':'n','š':'s','ū':'u','ž':'z','ө':'o','ң':'n','ү':'u','ә':'a','ғ':'g','қ':'q','ұ':'u','ა':'a','ბ':'b','გ':'g','დ':'d','ე':'e','ვ':'v','ზ':'z','თ':'th','ი':'i','კ':'k','ლ':'l','მ':'m','ნ':'n','ო':'o','პ':'p','ჟ':'zh','რ':'r','ს':'s','ტ':'t','უ':'u','ფ':'ph','ქ':'q','ღ':'gh','ყ':'qh','შ':'sh','ჩ':'ch','ც':'ts','ძ':'dz','წ':'ts','ჭ':'tch','ხ':'kh','ჯ':'j','ჰ':'h','&':'-and-'};

  // Separate language settings
  if (lang === 'bg') Object.assign(abc, {'щ': 'sht', 'ъ': 'a'});
  if (lang === 'uk') Object.assign(abc, {'и': 'y', 'г': 'h'});

  // Start transliteration
  str = str.toLowerCase();

  // Replace characters
  const pattern = new RegExp(`[${Object.keys(abc).join('')}]`, 'g');
  str = str.replace(pattern, ch => abc[ch] ?? ch);

  // Remove all non-alpanumeric symbols
  str = str
    .replace(/[^a-z0-9]+/g, delimiter)     // replace everything except latin and nubers
    .replace(new RegExp(`${delimiter}{2,}`, 'g'), delimiter) // remove double dashes
    .replace(new RegExp(`(^${delimiter}|${delimiter}$)`, 'g'), ''); // remove dashes at the end and the beginning

  return str;
}

/**
 * Copy/paste string from 
 * @param {String|Element} source An input, to copy value from or a string to be inserted in target input
 * @param {Element} targetSelector CSS selector of target input where @param text will be inserted
 * @param {Boolean} replaceExisting Wether to replace existing targetInput text or not
 * @param {Function} callback A function to apply to target and source elements
 * @returns 
 */
function pasteString(source, targetInputs, replaceExisting = true, callback) {

  let text, sourceInput;
  if (!targetInputs.length) {
    // Assume it is single element from querySelector, not querySelectorAll. Put it to array so it can be iterated
    targetInputs = [targetInputs];
  }

  if (typeof(source) === "string") {
    text = source;
    sourceInput = false;
  }

  if (source instanceof Element) {
    text = source.value;
    sourceInput = source;
  }

  const hasText = Boolean(text);

  targetInputs.forEach(target => {
    // Highlight source and target inputs
    if ((replaceExisting || !target.value) && !target.disabled) {
      toggleAlert(sourceInput, hasText);
      toggleAlert(target, hasText);
    }
    // Set target input value
    if (hasText && (replaceExisting || !target.value) && !target.disabled) {target.value = text}
  });

  function toggleAlert(el, isSuccess) {
    if (!el) return;
    el.classList.toggle('alert-success', isSuccess);
    el.classList.toggle('alert-danger', !isSuccess);
  }
  if (callback) {
    callback(targetInputs, sourceInput);
  }
}

/**
 * Translate string using Google translate API
 * @param   {String} text String to be translated
 * @param   {String} targetLang target language code
 * @param   {String} sourceLang sourche language code
 * @param   {Number} attempt Attempt number to avoid ban
 * @returns {String} Translated string
 */
async function googleTranslate(text, targetLang = 'en', sourceLang = 'auto', attempt = 1) {
  if (!text) {return ''}
  const base = 'https://translate.googleapis.com/translate_a/single';
  const params = new URLSearchParams({
    client: 'gtx',
    sl: sourceLang,
    tl: targetLang,
    dt: 't',
    q: text,
  });

  const url = `${base}?${params.toString()}`;

  // Promise timeout to avoid ban
  await new Promise(resolve => setTimeout(resolve, attempt > 1 ? (700 + Math.random() * 500) : 0));

  try {
    const res = await fetch(url);

    // If Google return code  is 429, 503 then it looks like our request looks suspicious
    if (!res.ok) {
      console.warn(`⚠️ Attempt ${attempt}: HTTP ${res.status} ${res.statusText}`);
      if (attempt < 3) {
        // Retry after 3-5 seconds
        await new Promise(resolve => setTimeout(resolve, 2000 + Math.random() * 3000));
        return googleTranslate(text, targetLang, sourceLang, attempt + 1);
      } else {
        throw new Error(`⚠️ Google Translate refused after ${attempt} attempts`);
      }
    }

    const raw = await res.text();

    // Check if response contains captcha
    if (raw.includes('<html') || raw.includes('automated queries')) {
      console.warn('⚠️ Looks like Google returned captha or blocked the request. Try again later');
      return null;
    }

    // Parse JSON
    const data = JSON.parse(raw);
    const translated = data?.[0]?.[0]?.[0] ?? null;

    console.log(`Translate ${text} from ${sourceLang} to ${targetLang}. Result: ${translated}`);
    return translated;

  } catch (err) {
    console.error(`Request error (attempt ${attempt}):`, err);
    if (attempt < 3) {
      await new Promise(resolve => setTimeout(resolve, 3000 + Math.random() * 2000));
      return googleTranslate(text, targetLang, sourceLang, attempt + 1);
    }
    return null;
  }
}

/**
 * Translate array of texts using Google translate API
 * @param   {Array}  texts An array of texts to be consequently translated
 * @param   {String} targetLang target lang code
 * @param   {String} sourceLang Source lang code
 * @returns {Array}  An array of translated texts
 * Usage:
 * translateArray([
 *   'Hello world!',
 *   'This text will be transladed seqentially',
 *   'from English to Ukrainian'
 * ], 'uk', 'en'
 */
async function translateArray(texts, targetLang = 'en', sourceLang = 'auto') {
  const results = [];
  for (const text of texts) {
    const translated = await googleTranslate(text, targetLang, sourceLang);
    results.push({ text, translated });
  }
  console.table(results);
  return results;
}

// Translate buttons event listeners
document.addEventListener('DOMContentLoaded', async () => {
  document.addEventListener('click', async e => {
    // Translate string
    if (e.target.closest('.translateString')) {
      const targetInput = e.target.closest('div').querySelector('input, textarea');
      const defaultInput = document.querySelector(`[name="${targetInput.name.replace(/\[\d+\](\[[^\]]+\])$/, `[${defaultLanguage.language_id}]$1`)}"]`);
      const text = targetInput.value || defaultInput.value || '';
      targetInput.classList.toggle('alert-danger', text === '');
      
      try {
        let translatedText = await googleTranslate(
          text,
          targetInput.dataset.languageCode, 
          defaultLanguage.code.split('-')[0]
        );
        if (translatedText) {
          targetInput.value = translatedText;
          targetInput.classList.add('alert-success');
        }
      } catch(e) {
        console.log(e)
      }
    }

    // Paste values from adjacent store
    if (e.target.closest('.pasteAdjacent')) {
      const input = e.target.closest('div').querySelector('input, textarea');
      if (input.value) {return}
      input.value = input.placeholder;
    }
  });
  document.addEventListener('input', e => {
    // Set select background and text color if option has corresponding inline style 
    if (e.target.tagName === 'SELECT') {
      e.target.style.backgroundColor = e.target.selectedOptions[0].style?.backgroundColor;
      e.target.style.color = e.target.selectedOptions[0].style?.color;
      e.target.style.borderColor = e.target.selectedOptions[0].style?.color;
    }
  });
});

/**
 * Check URL duplpicates by URL, store_id and language_id
 * @param   {Element}   input SEO URL input
 * @param   {Function}  callback Function to highlight input or do seomething else
 * @returns {Array} of duplicate URLs
 */
async function checkUrlDuplicates(input) {
  if (input.disabled) return;

  try {
    input.closest('.input-group').parentElement.querySelector('.duplicateUrlError')?.remove();
    const keyword = input.value?.trim();
    if (!keyword) return;
    // input.classList.toggle('alert-danger', !keyword);

    const languageId = input.dataset.languageId || '';
    const request    = input.dataset.request || '';

    // Concat fetch URL
    let url = `/admin/index.php?route=design/seo_url/fetchCheckUrlDuplicate&user_token=${userToken}`;
    const body = new FormData();
    body.append('languageId', languageId);
    body.append('url', keyword);
    body.append('request', request);

    const result = await fetch(url, {method: "POST", body}).then(r => r.json());
    console.log('checkUrlDuplicates: ', result);
    let hasError = !!Object.keys(result.response.duplicateCheck).length;

    if (hasError) {
      let errorHtml = `<div class="text-danger duplicateUrlError">${result.errorMessage}</div>`;
      input.closest('.input-group')?.parentElement.insertAdjacentHTML('beforeend', errorHtml);
    }
    input.closest('.input-group')?.classList.toggle('has-error', hasError)
    input.classList.toggle('alert-success', !hasError)
    input.classList.toggle('alert-danger', hasError)

  } catch (e) {
    console.error('checkUrlDuplicates():', e);
  }
}

/**
 * Recursively create element from Object
 * @param   {Object} config An object with regular {Element} JS attributes
 * @returns {Element}
 * Usage:
 * const myElement = createElm({
 *   tagName: 'div',
 *   id: 'main-container',
 *   name: 'some_name[some_other_name]',
 *   className: 'form-control',
 *   style: {backgroundColor: 'lightblue', padding: '20px'},
 *   dataset: {searchColumn: 'language_id'},
 *   attributes: {'aria-label': 'main content area'},
 *   children: [
 *     {
 *        tagName: 'select',
 *        name: 'someData[someOtherData]',
 *        dataset: {selectId: '123'},
 *        className: "form-control",
 *        value: '2',
 *        children: [{tagName: 'option', textContent: 'Option 1 label', value: '1'}, {tagName: 'option', textContent: 'Option 2 label', value: '2'}]
 *     },
 *     {
 *        tagName: 'p',
 *        textContent: 'Some text inside <p>',
 *     },
 *     {
 *        tagName: 'button',
 *        textContent: 'Click Me',
 *        onclick: () => alert('Button clicked!')
 *     }
 *  ]
 * });
 */
function createElm(config) {
  if (!config || !config.tagName) {throw new Error("createElm: 'tagName' is required")}

  const el = document.createElement(config.tagName.toLowerCase());

  if (config.dataset)     { Object.assign(el.dataset, config.dataset) }
  if (config.style)       { Object.assign(el.style, config.style) }
  if (config.attributes)  { for (const attr in config.attributes) { el.setAttribute(attr, config.attributes[attr]) }}

  // Children
  if (Array.isArray(config.children)) {
    for (const child of config.children) {
      if      (typeof child === "string") { el.appendChild(document.createTextNode(child)) }
      else if (child instanceof Node) { el.appendChild(child) }
      else if (typeof child === "object" && child.tagName) {
        const childEl = createElm(child);
        // Set selected state for outerHTML
        if (config.value && child.value && config.value == child.value) { childEl.setAttribute("selected", "") }
        el.appendChild(childEl);
      }
    }
  }

  // Event handlers
  for (const key in config) {
    if (key.startsWith("on") && typeof config[key] === "function") { el[key] = config[key] }
  }

  // Primitive properties
  const skip = new Set(["tagName", "dataset", "style", "attributes", "children", "value"]);

  for (const key in config) {
    if (skip.has(key)) continue;

    const val = config[key];

    // Set primitives
    if (typeof val !== "object" && typeof val !== "function") {
      try { el[key] = val } catch (e) {}
    }
  }

  // Set elelment value after all children are added (in case of select element)
  if (config.value !== null) { el.value = config.value }

  return el;
}

/**
 * Add cloned row to target table
 * Used to add rows in tables like: tab_image_list.twig, tab_faq_list.twig, tab_how_to_list.twig, tab_footer_list.twig, filter_form.twig, meta_editor_list.twig and more
 * @param {Element} button Button in tfoot of table, where rows should be cloned
 * @param {String} tableSelector Optional table selector, where rows will be cloned and added
 * @param {String} rowSelector Optional row selector
 * @param {Array} excludedInputClasses An array of classes of inputs which values would not be cleared. E.g. some hidden inputs with some crucial ids 
 * @returns 
 */
function addRow2(button, tableSelector = '.cloneRowsTable', rowSelector = '[data-row-index]', excludedInputClasses = []) {
  const table = button.closest(tableSelector);
  if (!table) return;

  const rows = table.querySelectorAll(rowSelector);
  if (!rows.length) return;

  const targetRow = rows[rows.length - 1];
  const oldRowIndex = targetRow.dataset.rowIndex;
  const newRowIndex = Number(oldRowIndex) + 1;

  const newRow = targetRow.cloneNode(true);
  newRow.dataset.rowIndex = newRowIndex;

  // Element [data-nameprefix] is a constant string which avoids row increment
  // Needed to keep [store_id] and [language_id] unchanged
  // Example: 
  // name="category_description[0][1][faq][0][question]"
  // data-nameprefix="category_description[0][1]"
  // In this case only [0] in [faq][0][question] will be incremented
  newRow.querySelectorAll('input, select, textarea, a, img, [data-source-input]').forEach(el => {
    // Name elements - all kind of inputs
    if (el.name) {
      if (el.dataset.nameprefix) {
        const oldName = targetRow.querySelector(`[name="${el.name}"]`).name;
        el.name = `${el.dataset.nameprefix}${oldName.replace(el.dataset.nameprefix, '').replace(`[${oldRowIndex}]`, `[${newRowIndex}]`)}`
      } else {
        el.name = el.name.replace(`[${oldRowIndex}]`, `[${newRowIndex}]`);
      }
    }

    // Translate buttons
    if (el.dataset.nameprefix && el.dataset.sourceInput) {
      const oldSourceInput = el.dataset.sourceInput;
      el.dataset.sourceInput = `${el.dataset.nameprefix}${oldSourceInput.replace(el.dataset.nameprefix, '').replace(`[${oldRowIndex}]`, `[${newRowIndex}]`)}`
    }

    // Replace the last occurance of row index
    // Fixes bug when the id is like "someId_store_0_language_1_rowIndex_0"
    // For popup with image selector
    if (el.id) {
      // el.id = el.id.replace(oldRowIndex, newRowIndex);
      el.id = el.id.substring(0, el.id.lastIndexOf(oldRowIndex)) + newRowIndex;
    }
    if (el.value && !excludedInputClasses.some(excludedClass => Array.from(el.classList).includes(excludedClass)) && !el.name?.includes('sort_order')) {el.value = '';}
    if (el.src && el.dataset.placeholder) {el.src = el.dataset.placeholder;}
    if (el.name?.includes('sort_order')) {el.value++;}
    if (['radio', 'checkbox'].includes(el.type)) {el.checked = false;}
    el.classList.remove('alert-success', 'alert-danger');
  });

  newRow.querySelectorAll('.note-editor').forEach(el => {
    el.remove();
  });

  newRow.querySelectorAll('[data-toggle="summernote"]').forEach(el => {
    $(el).summernote(window.summernoteOptions);
  })

  targetRow.insertAdjacentElement('afterend', newRow);
}

/**
   * Remove row from target table
   * Pairs with addRow2() function
   * @param {Element} button Button inside row that should be removed
   * @param {String} tableSelector Table selector where rows will be interacted
   * @param {String} rowSelector Optional row selector
   */
function removeRow2(button, tableSelector = '.cloneRowsTable', rowSelector = '[data-row-index]') {
  const row = button.closest(rowSelector);
  const matchingRows = document.querySelector(tableSelector).querySelectorAll(rowSelector);
  // Clear last row values instead of deleting
  if (matchingRows.length <= 1) {
    row.querySelectorAll('input, select, textarea, img')?.forEach(el => {
      if (el.value) el.value = '';
      if (el.src && el.dataset.placeholder) el.src = el.dataset.placeholder;
    });
    return;
  }
  row.remove();
}
