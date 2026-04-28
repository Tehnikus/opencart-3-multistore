<?php
// Heading
$_['meta_editor']                   = 'SEO Meta editor';
$_['header_category']               = 'Categories';
$_['header_product']                = 'Products';
$_['header_manufacturer']           = 'Manufacturers';
$_['header_article']                = 'Articles';
$_['header_tag']                    = 'Blog tags';
$_['header_filter_page']            = 'Landing pages';
$_['header_formaulas']              = 'Generation formulas';
$_['column_formulas']               = 'Formulas';

// Inputs
$_['input_search']                  = 'Search';
$_['input_h1']                      = 'H1';
$_['input_meta_title']              = 'Title';
$_['input_meta_description']        = 'Description';
$_['input_meta_description_short']  = 'Desc';
$_['input_faq']                     = 'FAQ';
$_['input_how_to']                  = 'HowTo';
$_['input_description']             = 'Description';
$_['input_seo_description']         = 'SEO Description';
$_['input_footer']                  = 'Footer';
$_['input_seo_keywords']            = 'Keywords';
$_['input_formula']                 = 'Supported tags: {{name}}, {{price}}, {{minPrice}}, {{maxPrice}}, {{discount}}, {{shop}}, {{category}}, {{rating}}, {{reviews}}, {{offers}}';

// Buttons
$_['button_save']                   = 'Save';
$_['button_save_all']               = 'Save everything';
$_['button_add_formula']            = 'Add the formula';
$_['button_remove_formula']         = 'Delete the formula';
$_['button_generate']               = 'Generate';
$_['button_undo']                   = 'Undo';
$_['button_undo_all']               = 'Undo all';
$_['button_edit']                   = 'Edit page (new tab)';
$_['button_clear_filters']          = 'Clear filters';

// Options
$_['option_language']               = 'Language';
$_['option_state']                  = 'State';
$_['option_state_updated']          = 'Updated';
$_['option_state_saved']            = 'Saved';
$_['option_state_hasError']         = 'Has error';
$_['option_selection']              = 'Selection';
$_['option_selection_selected']     = 'Selected';
$_['option_selection_not_selected'] = 'Not selected';

// Messages
$_['message_metadata_saved']        = 'Metadata saved';
$_['message_formulas_saved']        = 'Formulas saved';
$_['message_saved']                 = 'Saved';
$_['message_loaded']                = 'Loaded';
$_['message_select_rows']           = 'Select pages to be saved';

// Help
$_['help_syntax_hint']              = 'Tag syntax: <b>{{tag1:tag2:"or a tag replacement"|filter}}</b>. <br>';
$_['help_show_manual']              = 'Show the instructions';
$_['help_syntax_example']           = '<h4>Basic syntax:</h4>
                                      <ul class="text-info">
                                        <li><b style="user-select: all">{{minPrice:"best price"}}</b> will output the price in the selected currency or line in quotation marks after the colon "best price" if the minPrice is not set</li>
                                        <li><b style="user-select: all">{{category:"other products"|lower}}</b> will output the parent category of product/category starting with a small letter or line inside quotes "<b>other products</b>"</li>
                                        <li><b style="user-select: all">{{store|upper}}</b> will output the name of the store in CAPITAL letters</li>
                                        <li>Consequent tags are also supported: <b>{{discount:price:"The best price"}}</b></li>
                                      </ul>
                                      <p>Supported filters: <b>upper</b> - CAPITAL letters, <b>lower</b> - small letters, <b>capitalize</b> - with a Capital letter</p>';
$_['help_tag_hint']                 = '<h4>Supported tags:</h4>
                                        <ul class="text-info">
                                          <li><b style="user-select: all">{{name}}</b> - name</li> 
                                          <li><b style="user-select: all">{{store}}</b> - store</li> 
                                          <li><b style="user-select: all">{{category}}</b> - parent category</li> 
                                          <li><b style="user-select: all">{{price}}</b> - the base price without discount</li> 
                                          <li><b style="user-select: all">{{discount}}</b> - discount</li> 
                                          <li><b style="user-select: all">{{minPrice}}</b> - the minimum price of the goods if the product has options or the lowest products price in the category </li> 
                                          <li><b style="user-select: all">{{maxPrice}}</b> - the same, but the highest price</li> <li><b style="user-select: all">{{rating}}</b> - average rating of the product or the category</li> 
                                          <li><b style="user-select: all">{{reviews}}</b> - reviews count</li> 
                                          <li><b style="user-select: all">{{offers}}</b> - the number of product vaiants by options or product count in the category</li>
                                        </ul>';

