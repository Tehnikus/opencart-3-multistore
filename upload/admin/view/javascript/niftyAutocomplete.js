/**
* niftyAutocomplete - easy flexible and lightweight autocomplete written in pure JavaScript
* Author https://github.com/Tehnikus
*/

class niftyAutocomplete {
  constructor(options) {

    // options = {
    //   idKey,
    //   input,
    //   selectedItemsBox,
    //   request.url,
    //   request.getParams,
    //   request.postParams,
    //   responseCallback,
    //   item.template,
    // }

    Object.assign(
      this, 
      {
        navKeys: ['ArrowDown', 'ArrowUp', 'Enter', 'Escape', 'Tab'],
        ignoredKeys: ['Shift', 'Control', 'Alt', 'CapsLock', 'ArrowLeft', 'ArrowRight'],
        currentIndex: -1,
        items: [],
        debounceTimer: null,
        // Item templates
        item: {
          searchTemplate: (row) => `<div><p><b>${row[options.idKey]}</b></p></div>`,
          selectTemplate: (row) => `<div><p><b>${row[options.idKey]}</b></p></div><div><button type="button" class="niftyItemDelete">&times;</button></div>`,
        },
        // Behavior can be overwritten by options
        behavior: {
          clearSearchOnSelect: true, // Do not close results box after one item was selected. Handy for multiple selects
          maxSelected: null,
        }
      },
      options
    );

    if (!this.input) return;

    // Add results container
    if (!this.resultBox) {
      this.resultBox = document.createElement('div');
      this.input.insertAdjacentElement('afterend', this.resultBox);
    }
    if (!this.resultBox.classList.contains('niftySearch')) {
      this.resultBox.classList.add('niftySearch');
    }

    // Add selected items container
    if (!this.selectedItemsBox) {
      this.selectedItemsBox = document.createElement('div');
      this.input.insertAdjacentElement('afterend', this.selectedItemsBox);
    }
    if (!this.selectedItemsBox.classList.contains('niftySelected')) {
      this.selectedItemsBox.classList.add('niftySelected');
    }

    this.initAutocomplete();

  }

  initAutocomplete() {
    if (this.input.dataset.autocompleteInitialized) { return }
    // this.input.parentElement.style.position = 'relative';
    
    ['keyup', 'keydown', 'focusin'].forEach(eventType => {
      this.input.addEventListener(eventType, (e) => {
        if (e.type === 'keydown') {
          if (this.navKeys.includes(e.key) && this.items.length > 0) {
            if (e.key === 'ArrowDown') this.currentIndex = (this.currentIndex + 1) % this.items.length;
            else if (e.key === 'ArrowUp') this.currentIndex = (this.currentIndex - 1 + this.items.length) % this.items.length;
            else if (e.key === 'Enter') { e.preventDefault(); if (this.currentIndex >= 0) this.items[this.currentIndex].click(); }
            else if (['Escape', 'Tab'].includes(e.key)) this.clearResults();
            this.highlightItem();
            return;
          }
        }

        if (['keyup', 'focusin'].includes(e.type) && !this.navKeys.includes(e.key) && !this.ignoredKeys.includes(e.key)) {
          clearTimeout(this.debounceTimer);
          // Prevent requesting when typing
          this.debounceTimer = setTimeout(() => this.performSearch(), 100);
        }
      });
    });

    // Close on click outside
    document.addEventListener('mousedown', (e) => {
      if (!this.resultBox.contains(e.target) && e.target !== this.input) {
        this.clearResults();
      }
    });

    this.input.dataset.autocompleteInitialized = 'true';
  }

  async performSearch() {
    // Clear previous results
    this.clearResults();

    try {
      let fetchParams = {};
      let data;
      const url = new URL(this.request.url);

      Object.keys(this.request.getParams || {}).forEach(key => {
        url.searchParams.set(key, (typeof this.request.getParams[key] == 'function') ? this.request.getParams[key]() : this.request.getParams[key]);
      });


      if (this.request.postParams) {
        const formData = new FormData();
        for (const key in this.request.postParams) {
          formData.append(key, (typeof this.request.postParams[key] == 'function') ? this.request.postParams[key]() : this.request.postParams[key]);
        }
        fetchParams = {method: 'POST', body: formData}
      }

      const response = await fetch(url, fetchParams);
      data = await response.json();

      if (this.responseCallback) {
        data = this.responseCallback(data);
      }

      // Get already selected items
      const selectedIds = this.selectedItemsBox ? Array.from(this.selectedItemsBox.querySelectorAll('[data-id]')).map(el => el.dataset.id) : [];

      for (let row of data) {
        const id = String(row[this.idKey] ?? '');
        if (selectedIds.includes(id)) continue;

        const wrapper = this.renderSearchItem(row);

        wrapper.addEventListener('click', () => {

          // Apply onSelectCallback if present
          const rows = this.onSelectCallback ? this.onSelectCallback(row) : [row];
        
          rows.forEach(r => this.renderSelectedItem(r));

          // Close search box on select
          if (this.behavior.clearSearchOnSelect) {
            this.clearResults();
          } else {
            // If search box is not closed then highlight next item
            // Remove clicked item from items index, so highest currentIndex number is equal to items length
            this.items.splice(this.currentIndex, 1);
            // No more items, close results
            if (this.items.length === 0) {
              this.clearResults();
            }
            // Last item in search result selected
            if (this.currentIndex >= this.items.length) {
              if (this.items[this.currentIndex - 1]) {
                // Try to highlight previous item
                this.currentIndex--;
              } else {
                // Or highlight first
                this.currentIndex = 0;
              }
            }

            this.highlightItem();
          }

          // Clear previously selected items for cases when only one item 
          if (this.behavior.maxSelected) {
            const selected = this.selectedItemsBox.querySelectorAll('[data-id]');
            if (selected.length === this.behavior.maxSelected) {
              this.clearResults();
            }
            if (selected.length > this.behavior.maxSelected) {
              selected[0].remove();
            }
          }
          wrapper.remove();
        });
      }

    } catch(e) {
      console.error(e)
    }
  }

  renderSearchItem(row) {
    // Create item element
    const wrapper = document.createElement('div');
    wrapper.classList.add('niftyItem');
    wrapper.dataset.id = row[this.idKey];
    wrapper.innerHTML = this.item.searchTemplate(row);

    wrapper.addEventListener('mouseenter', () => {
      this.item?.callbackHover?.(wrapper);
      this.currentIndex = this.items.indexOf(wrapper);
      this.highlightItem();
    });

    // Add item to results box
    this.resultBox.appendChild(wrapper);
    this.items.push(wrapper);
    return wrapper;
  }

  renderSelectedItem(row) {
    // Create item element
    const template = document.createElement('template');
    template.innerHTML = this.item.selectTemplate(row);

    const wrapper = template.content.firstElementChild;
    
    wrapper.classList.add('niftyItem');
    if (row[this.idKey]) {wrapper.dataset.id = row[this.idKey]}
    wrapper.addEventListener('click', e => {
      if (e.target.closest('.niftyItemDelete')) {
        this.item?.callbackRemove?.(row);
        wrapper.remove();
      }
      // Click callback
      this.item?.callbackClick?.(wrapper);
    });

    // Add item to selected box
    this.selectedItemsBox.insertAdjacentElement('beforeend', wrapper);
  }

  clearResults() {
    this.resultBox.innerHTML = '';
    this.items = [];
    this.currentIndex = -1;
  }

  highlightItem() {
    this.items.forEach((item, index) => {
      item.classList.toggle('active', index === this.currentIndex);
    });
    this.items?.[this.currentIndex]?.scrollIntoView({block: 'nearest', inline: 'nearest'});
  }
}
