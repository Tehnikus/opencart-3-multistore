# OpenCart 3 Multistore

## Overview

Opencart 3 Multistore is a deep modified version of Opencart 3 with full support of **multistore** features and multiple additions aiming for speed, best SEO practices and latest PHP/MySQL support.

## Features
Below is the list of implemented features which will extend as I add more.
### Full Multistore Support
This version of Opencart 3 fully supports **multistore** meaning one instance of store can run multiple and fully separate sites. Catalog - products, categories and their features may or may not be shared between the stores/ Features supported:
 - Separate product descriptions and SEO tags
 - Separate categories descriptions and SEO tags
 - Separate product list in each category
 - Separate product prices, including specials and discounts, meaning one product may have different price or discount in every store
 - Separate category trees, meaning any category may have any parent or child in store context
 - Separate or shared list of options, attributes and filters
 - Separate product and category images for each store
 - Separate product and category statuses
 - Separate extensions and settings
 - Separate blog, posts 
 - Separate store design and theme
 - Separate store addresses and contacts
... and so on. 
Some convenient features are shared between stores such as products stock numbers. This system is aimed to run different stores in one place without clutter/

### Facet Filter with SEO Support
While original Opencart product filter is very basic and not filtering products correctly, mine version has built-in **Facet filter** that implements product features intersection and supports any feature related to product:
 - Product options
 - Product attributes
 - Product filters
 - Product parent categories
 - Product manufacturer
 - Availability
 - Discounts
 Other features such as suppliers and SEO tag are to be implemented in future.
 Second major feature is result ordering which has some advanced orders like 
 - Trending products (all time) - calculated on the fly according to product orders, returns, reviews and views
 - Trending products (currently) - same as all time trending, but takes in account latest order and review **dates** with different with coefficients 
 - Price, discount, rating, orders, new products and more
 The **Facet filter** supports SEO links meaning every facet page is available for indexing. Best practice is to limit indexing by number of active facets which is also supported and configurable.
And most importantly the Facet filter works very, **VERY fast**: 
- 0,1 millisecond to filter products
- 2-3 milliseconds to build facet list and count product intersections for every facet 

### Facet Filter SEO Pages
Facet filter SEO page can be created by combining active facets. Such pages support every SEO tag and separate description, which is needed for better ranking. This means when customer selects certain combination of facets and/or product sort the distinct SEO page is displayed, which can be indexed by search engines greatly improving overall site SEO ranking. Certainly these pages are separate for each store along with their descriptions and other SEO data.

### JSON-LD Microdata
Every major page has valid microdata description enabling **Rich snippets** in search engines results page (SERP). This includes:
 - Products
 - Categories
 - Facet filter SEO pages
 - Home page
 - Contacts
 ... and others.
Also my Opencart 3 multistore has two built-in microdata features: 
 - **FAQ builder** which helps to create frequent questions and answers and may be shown in SERP, which improves conversions in it's turn
 - **How-To builder** which may also be indexed and shown in SERP, improve conversions and serve like the manual for customers

## SEO Features

### Perfect SEO URLs
While original Opencart 3 has very basic SEO URL support and long lasting problems with content duplicates my reworked version has everything covered and fixed for best SEO results:
 - No content duplicates
 - Redirect to canonical URLs
 - Whitelisted parameters 
These features are fixing most annoying and complicated original Opencart problem, when the same page could be accessed from different URLs leading to search engines indexing nightmare.
Also to support Facet filter some features have added their own SEO URLs:
 - Product filters
 - Product options
 - Product attributes
 - Sort orders
 - Pagination
Overall result is that one canonical page can be accessed only by one URL.

### SEO Keywords Controller
The SEO Keywords controller allows keywords to be imported or entered by hand and create cross-linking between pages. This feature implements intuitive interface and fast search of keyword. Thus, you can just import CSV keyword list and cross-link your pages - products, categories and filter pages to improve search engines ranking by most valuable keywords. SEO keywords are separated by store, language and keyword groups for convenience

### SEO Meta Editor
SEO Meta Editor controller created to edit SEO meta tags of products, categories and filter SEO pages quickly and easily in one place. It also supports **FORMULAS** to generate meta, which may include these entities:
 - Name
 - Price, lowest and highest
 - Product count for category and filter SEO page 
 - Availability
 - Manufacturer name
 - Parent name - product's category or category parent
 - Discounts
 - Store name
Tags available for editing:
 - Title
 - Description
 - H1
Everything is gathered in convenient and easy to use interface

## Speed
While Opencart 3 is rather lightweight compared to other CMS it lacks optimization, caching and often uses heavy queries in loops. My **Opencart Multistore** has most models code rewritten, tested and optimized for the best performance aiming to deliver any page in less than 100 milliseconds.

### Model and Caching
Most frontend models are reworked and tested to deliver best performance.
The most vital model results are cached by built-in data cache system.
Caching can be achieved by using built-in adapters:
 - file (original)
 - **NEW** fastfile (better than original because uses faster invalidation process by timestamp rather than file extension and puts files into separate folders to avoid exceeding the server inode limit)
 - **NEW** Redis cache
 - APCU
 - Memcached
This allows lowering latency and page delivering time
### Twig Static Cache
Twig has it's own built-in static cache extension **twig/cache-extra** that creates chunks of rendered HTML and delivers them on demand. They can be stored as long as not invalidated. The right way to invalidate is the last entity update timestamp, which is used as cache "tag". This mechanism invalidates HTML chunks automatically and delivers best performance without intrusion in site workflow and appearance issues

### jQuery Is Removed from the Frontend
Who in good sanity will keep in frontend jQuery in modern day? Removed and replaced by vanilla JavaScript which is faster, non page-blocking and securer.

### Bootstrap is removed
While you may argue that CSS framework is mandatory, what if I told you that you can squeeze all the necessary styles in 20 kilobytes instead of 300 including responsive design, color scheme system and animations? That's the right way.