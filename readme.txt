=== WooCommerce Dynamic Sorting ===
Contributors: phill_brown
Donate link: http://pbweb.co.uk/donate
Tags: woocommerce, sorting, orderby, products, shop
Requires at least: 3.1
Tested up to: 3.8
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WooCommerce plugin that links up with Google Analytics to sort products based on page views and visits.

== Description ==

Dynamic Sorting is a lightweight WooCommerce plugin that links up with your Google Analytics account and allows you to sort products based on a website’s usage statistics. Out the box, it adds two ordering options – sort by views and sort by visits.

Google Analytics sorting options can be hidden from the user facing sorting dropdown. This means you can set the default sorting order to feature your shop’s most popular products without your customer knowing.

There is also a premium version of the plugin called [Sort by Google Analytics](http://pbweb.co.uk/sort-by-ga-shop) that lets you sort any post type by any Google Analytics metric. WooCommerce Dynamic Sorting works with the product post type and supports sorting by pageviews and visits.

**The Indexer**

For performance reasons the plugin operates off a locally downloaded index of Google Analytics data. The indexer runs twice a day and you can trigger it manually through the Dynamic Sorting Settings section in the Catalog tab in WooCommerce Settings. Once the indexer has run at least once, the plugin is ready to use.

To increase the batch of the indexer to 400, add the following code to your plugin or theme:

`add_filter( 'woocommerce_dynamic_sorting_indexer_data_batch_size', create_function( '', 'return 400;' ) );`

**Requirements**

* WordPress 3.1+
* WooCommerce 1.0+ plugin installed
* PHP cURL extension
* PHP JSON extension
* Google Analytics Account

== Installation ==

This walkthrough explains how set up the plugin and make your most viewed products appear first.

1. Extract the contents of the ZIP file into your `<root>/wp-content/plugins/woocommerce-dynamic-sorting` folder.
1. Login to the WordPress administration area.
1. Click 'Plugins' in the left hand main navigation menu.
1. Scroll down to *WooCommerce Dynamic Sorting* and click activate
1. There should be a notice at the top of your page. Click the click configuration link. Alternatively locate the WooCommerce link in the left hand main navigation menu and select *Settings* in the sub-menu. Select the catalog tab and scroll to the bottom of the page.
1. Get your Google Authentication Code by clicking the link below the text box. This will open a popup window asking for permission to use your Analytics data. **Your data is completely confidential - nobody ever see's it and it's only ever transferred between your website and Google.** Click accept.
1. The popup will display a code in a text box. Select the code and copy it to your clipboard by holding down CTRL + C (Windows) or Command + C (Mac).
1. Close the popup window and paste the code (CTRL/Command + V) into the Google Authentication Code field in the WooCommerce Dynamic Sorting settings. Click the *Save Changes* button.
1. You now need to select your Google Analytics profile for your current website. Choose your profile from the dropdown list and click the *Save Changes* button. Your page may take time to refresh as the plugin is indexing data in the background.
1. Two more fields will appear in the refreshed settings page. **Date range** allows to you to choose how far back your statistics will be accounted for. **Indexer status** allows you to manually update your local Google Analytics data. On larger WooCommerce shops, you may need to click this again to fully index your site.
1. Scroll to the top of the page to *Catalog Options* and select the dropdown labelled *Default Product Sorting*. There should be two new options - **most viewed** and **most visited**. Choose *most visitied*, scroll to the bottom of the page, and click *Save Changes*.
