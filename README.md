# Site Tree Importer Module

[![Build Status](https://secure.travis-ci.org/silverstripe-labs/silverstripe-sitetreeimporter.png?branch=master)](http://travis-ci.org/silverstripe-labs/silverstripe-sitetreeimporter)

## Maintainer Contacts

 * Sam Minnee (Nickname: sminnee)
   <sam (at) silverstripe (dot) com>
 * Michael Parkhill
   <mike (at) silverstripe (dot) com>

## Requirements

 * SilverStripe 3.0 or later

## Installation

No installation required.

## Usage

Make a tabbed-out file as directed in the form that appears below. Make sure that you use tabs, not spaces.
Visit `http://localhost/your-site-name/SiteTreeImporter?flush=1`.
Select your tabbed-out file in the file field, and tick the option boxes as appropriate:

*Clear out all existing content?*- This will delete everything from your site before running the importer.
Use with caution! If you don't tick this, then the pages will be added to the existing site.

*Publish everything after the import?*- This will publish each of the pages that the importer creates.
If you don't tick this, then the pages will be left as draft-only.

* Clear out all existing Redirected URLs?*- This will delete everything in the RedirectedURL table, use with caution!
If don't tick this option, you could have duplicate redirects created.

*Create Redirects for LegacyURL's?*- If you include the LegacyURL field data in the JSON format, this will create
a RedirectedURL entry for each page imported, redirecting from the LegacyURL to the imported page's url.


## Format

The site tree import module lets you take a tabbed out file of the following format,
and load it into your CMS as a site tree.

	Home
	About  {"URLSegment": "about-us", "MetaDescription": "About our company"}
		Staff
			Sam
			Sig
	Products
		Laptops
			Macbook
			Macbook Pro
		Phones
			iPhone  {"URLSegment": "apple-iphone", "LegacyURL": "/iphones"}

## Related

See the [static site connector module](http://addons.silverstripe.org/add-ons/silverstripe/staticsiteconnector) for a more sophisticated
importer based on crawling existing HTML pages, and extracting content via XPATH.

See the [redirected urls module](http://addons.silverstripe.org/add-ons/silverstripe/redirectedurls) for the
legacy url redirection feature.
