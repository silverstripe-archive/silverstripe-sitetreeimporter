# Site Tree Importer Module

[![Build Status](https://secure.travis-ci.org/silverstripe-labs/silverstripe-sitetreeimporter.png?branch=master)](http://travis-ci.org/silverstripe-labs/silverstripe-sitetreeimporter)

## Requirements

 * SilverStripe 4 or later

## Installation

No installation required.

## Usage

Make a tabbed-out file as directed in the form that appears below. Make sure that you use tabs, not spaces.
Visit `http://localhost/your-site-name/SiteTreeImporter?flush=1`.
Select your tabbed-out file in the file field, and tick the option boxes as appropriate:

*Clear out all existing content?* - This will delete everything from your site before running the importer.
Use with caution! If you don't tick this, then the pages will be added to the existing site.

*Publish everything after the import?* - This will publish each of the pages that the importer creates.
If you don't tick this, then the pages will be left as draft-only.


## Format

The site tree import module lets you take a tabbed out file of the following format,
and load it into your CMS as a site tree.

```yml
Home
About
	Staff
		Sam
		Sig
Products
	Laptops
		Macbook
		Macbook Pro
	Phones
		iPhone
```

You can optionally include JSON encoded metadata as the last part of a line,
which will automatically save to properties on the `Page` object.
This can be useful to determine URL paths or set custom titles.

```yml
Home
	About {"URLSegment": "about-us", "MetaDescription": "About our company"}
	Contact {"URLSegment": "contact-us"}
```


## Howto

### Redirect URLs

Often an existing tree will need to be imported with URLs which map to different URLs on the new site.
While you could assign those manually to a `.htaccess` based redirect, we found it useful to
store the old URL straight in the `Page` object, and use SilverStripe's routing to handle the redirect.

In this example, we'll use the ["redirected urls" module](http://addons.silverstripe.org/add-ons/silverstripe/redirectedurls),
which routes based on a new data type called `RedirectedURL`. In order to create it,
we add a custom setter to the `Page` class, which gets called automatically if
a key named `LegacyURL` is found in the imported JSON data.

```php
class Page extends SiteTree {
	public function setLegacyURL($url) {
		$url = Director::makeRelative($url);
		$urlBase = parse_url($url, PHP_URL_PATH);
		$urlQuerystring = parse_url($url, PHP_URL_QUERY);

		$urlObj = RedirectedURL::get()->filter(array(
			'FromBase' => $urlBase,
			'FromQuerystring' => $urlQuerystring
		))->First();
		if(!$urlObj) {
			 $urlObj = new RedirectedURL();
		}
		$urlObj->FromBase = $urlBase;
		$urlObj->FromQuerystring = $urlQuerystring;

		if(!$this->URLSegment) {
			$this->URLSegment = $this->generateURLSegment($this->Title);
		}
		$urlObj->To = $this->RelativeLink();

		$urlObj->write();
	}
}
```
Now you can import a tree based on the following data:

```yml
Home
	About {"LegacyURL": "/old-about-location"}
	Contact {"LegacyURL": "/old-contact-location"}
```

## Related

See the [static site connector module](http://addons.silverstripe.org/add-ons/silverstripe/staticsiteconnector) for a more sophisticated
importer based on crawling existing HTML pages, and extracting content via XPATH.
