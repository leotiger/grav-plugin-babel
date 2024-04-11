# v1.1.6
## 11-04-2024

1. [](#improved)
    * Export hint
2. [](#bugfix)
    * Fix composer.json

# v1.1.5
## 11-04-2024

1. [](#bugfix)
    * Make valid composer files available to allow for side by side execution with tntsearch plugin

# v1.1.4
## 11-04-2024

1. [](#bugfix)
    * Create valid composer json file
    * Create valid and non-conflicting vendor/composer files

# v1.1.3
## 11-04-2024

1. [](#bugfix)
    * Make compatible with PHP version 8+
    * Update TNTSearch Vendor library

# v1.1.2
## 10-08-2018

1. [](#improved)
    * Add quicktray icon and make entry in admin menu sidebar optional (thx olevik)
    * remove unnecessary logging code
    * add hints to the interface
    * apply GRAV styles to some elements
    * remove bootstrap css
    * better documentation in README
1. [](#bugfix)
    * Copy correct existing translation into empty translation if user click on language identifier of existing translation
    * Fix compatibility issue: TNTSearch uses an older vendor version of the teamtnt/tntsearch (thx iusvar)


# v1.1.1
## 09-08-2018

1. [](#new)
    * Track theme variables
    * Add export for theme language packages
1. [](#bugfix)
    * Fix order of flat array elements with numeric keys.
    * Display flat array elements in correct order (keys ordered alphabetically and not numerically)

# v1.1.0
## 08-08-2018

1. [](#new)
    * Hide away complex system internals that include Regular Expressions to assure hassle-free usage of the plugin by less experienced site administrators
1. [](#bugfix)
    * Plugin domains, once translated with Babel, did not show status information correctly. Fixed.

# v1.0.4
## 08-08-2018

1. [](#bugfix)
    * Fixed indexing problem for variables with no parent group identifier aka domain. These are no routed into domain *unclassified*
    * Indexing as unclassified should fix as well wrong attributions and counts for the rest of the domains

# v1.0.3
## 08-08-2018

1. [](#new)
    * Zip exported domain definitions into language packs
    * Make domain language packs available in the Babel interface

# v1.0.2
## 08-08-2018

1. [](#improved)
    * Return to saving only edited (babelized) definitions
    * Track edited definitions on re-index keeping track forever

# v1.0.1
## 08-08-2018

1. [](#bugfix)
    * Save all definitions for merge to avoid losing previous translation work
    * Show changed translation in domain context, not only in babelized context
    * remove output test traces from Babel template

# v1.0.0
## 08-08-2018

1. [](#new)
    * Changelog started

