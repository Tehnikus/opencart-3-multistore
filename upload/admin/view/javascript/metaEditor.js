document.addEventListener('DOMContentLoaded', async ()=> {
  const interface = await fetch(`index.php?route=seo/meta_editor/fetchGetInterface&user_token=${user_token}`).then(r => r.json());
  const rows      = await loadBatch('seo/meta_editor','getPages', {type: pageType}, user_token, 100, null, false);
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

}
