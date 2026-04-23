
/**
 * Save data in batches accepts array of objects and uses model called method to save (or do anything else)  
 * @param {String} model      Route to the requested model, e.g. 'catalog/product'
 * @param {String} method     Method name 'addProduct'
 * @param {Array} data        Array of JSON object to be saved, e.g. [{some product data}, {other product data}]
 * @param {String} userToken  User token
 * @param {Number} batchSize  Size of batch to be tranfered until waiting next batch
 * @param {Function} callback A callback function that is fired when batch is saved
 * @param {Bool} debug        If true some verbose messages will be listed in console
 * @returns {Number} Total number of transfered rows 
 */
async function saveBatch(model, method, data = [], userToken, batchSize = 100, callback, debug = false, ...args) {
  let totalSaved = 0;

  for (let i = 0; i < data.length; i += batchSize) {
    const batch = data.slice(i, i + batchSize);

    const formData = new FormData();
    // batchloader.php will decode html entities
    formData.append('rows', JSON.stringify(batch));
    formData.append('args', JSON.stringify(args));

    const response = await fetch(
      `index.php?route=common/batchloader/saveBatch&model=${model}&method=${method}&user_token=${userToken}`,
      { method: 'POST', body: formData }
    );

    if (!response.ok) { console.error("saveBatch: Error while transfer: " + response.status); return; }

    const result = await response.json();

    if (debug) {console.info("saveBatch: Batch saved: ", result)}
    if (result.status !== "ok") { console.error("saveBatch: Server returned error: " + result.message); return; }

    totalSaved += result.saved ?? batch.length;
    if (callback) {callback(totalSaved)}
  }

  if (debug) {console.info(`saveBatch: Data transferred: `, data)}
  return totalSaved;
}

/**
 * Load data in batches from requested model and method
 * @param {String}   model     Route to the requested model, e.g. 'catalog/product'
 * @param {string}   method    Method name, e.g. 'getProducts'
 * @param {Object}   filter    Object of filters, that is accepted by requested model, e.g. {'filter_name': 'some product name', 'limit': '100'}
 * @param {String}   userToken user token
 * @param {Number}   batchSize Size of simultaneously loaded rows. Script will run until response is empty, adding every batch to total rows
 * @param {Function} callback  Callback function when response is ok
 * @param {Bool}     debug     if true some verbose messages will be listed in console
 * @returns {Array} An array of rows in JSON
 */
async function loadBatch(model, method, filter = {}, userToken, batchSize = 100, callback, debug = false) {
  let allRows = [];
  let batchIndex = 0;

  while (true) {
    let formData = new FormData();

    for (const key in filter) {
      if (filter[key] !== undefined && filter[key] !== null) {
        formData.append(key, filter[key]); 
      }
    }

    // Set start and limit
    if (!filter.start) { formData.append('start', batchIndex * batchSize) }
    if (!filter.limit) { formData.append('limit', batchSize) }

    if (debug) {
      const entries = {};
      for (const pair of formData.entries()) { entries[pair[0]] = pair[1] }
      console.info(`loadbatch: Model-> ${model}, Method-> ${method}, filter-> `, entries);
    }

    const response = await fetch(
      `index.php?route=common/batchloader/loadBatch&model=${model}&method=${method}&user_token=${userToken}`,
      { method: 'POST', body: formData }
    );

    if (response.status !== 200) {
      console.error(`loadBatch: Model-> ${model}, Method-> ${method}, Error while loading: ${response.status}`);
      const errorText = await response.text();
      console.log(errorText);
      return;
    }

    const result = await response.json();

    if (debug) { console.info(`loadBatch: Model-> ${model}, Method-> ${method}, Batch loaded: `, result) }
    if (result.status !== "ok") { console.error("loadBatch: Server returned error: " + result.message); return; }
    // Break while(true) if result is empty
    const isEmpty = Array.isArray(result.rows)
      ? result.rows.length === 0
      : Object.keys(result.rows).length === 0;
    if (isEmpty) { break }

    // Parse JSON if SQL returns prepared JSON string
    if (typeof result.rows === 'string') {
      try {
        result.rows = JSON.parse(result.rows);
      } catch(e) {
        console.error(`loadBatch: Model-> ${model}, Method-> ${method}, Error while parsing string: `, e);
      }
    }

    allRows = allRows.concat(result.rows);
    batchIndex++;
    if (callback) {callback(allRows)}
  }

  if (debug) {console.info(`loadBatch: Model-> ${model}, Method-> ${method}, Data loaded: `, allRows)}
  
  return allRows;
}
/**
 * Diplpay progress bar loading progress
 * @param {Element} progressbar progress bar element
 * @param {Number} currentCount current count
 * @param {Number} maxCount max count
 */
function progressCount(progressbar, currentCount, maxCount, message = '') {
  if (!progressbar || !currentCount || !maxCount) {return}
  progressbar.min = 0;
  progressbar.value = parseInt(currentCount);
  progressbar.max = parseInt(maxCount);
  document.querySelector(`[for="${progressbar.id}"]`).innerText = `${message} ${currentCount}/${maxCount}`;
}
