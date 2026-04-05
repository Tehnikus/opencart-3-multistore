

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
