# [Grav](http://getgrav.org) Babel

**If you encounter any issues, please don't hesitate
to [report
them](https://github.com/leotiger/grav-plugin-babel/issues).**


## What does this plugin offer?

As of version 1.0.0 this plugin offers a solution to index and edit all existing language variables configured in a GRAV instance
in one place. The plugin furthermore allows to export translation sets for domains and given languages. These files can be used to help
plugin and theme authors to complete their language sets. In the future this plugin may offer a shared repository for language definitions,
both for charging as well as storing language definitions in the context of plugins and themes.

## Introduction

GRAV did not dispose of an easy feature to handle and maintain the language definitions spread over all the system in plugins, themes, etc.

With the advent of the Babel plugin this changes a bit.

## Usage

To use the plugin you have to enable language support for you GRAV instance and you have to configure as well the languages you want to
handle with Babel in the plugin configuration. The languages handled by Babel do not have to be identical with the languages enabled for your
instance. This allows you to prepare additional languages before activating new ones for your site.

Once you have enabled the Babel languages you can index all language variables and existing translations. There is one magic keyboard shortcut
available Command/Ctrl + s which allows you to save right from within of the textarea field used to edit translations. 

Babel identifies edited and merged definitions when a re-index takes place. This allows the plugin to only merge edited definitions.

Important: Please merge your changes. Without merging changes are lost once you re-index.

You can export language packs based on domains, e.g. export translations sets related with PLUGIN_ADMIN. The language pack includes all exported
definitions. Please take into account that you have to trigger the export for each language. Export functionality is only available when a language domain
is active but not for all domains and not for the *babelized* filter.

The plugin offers three special filters that represent virtual domains:

* all domains
* babelized (items edited with the Babel plugin)
* unclassified

The last filter loads a collection of variables ( that 

## What's missing

* a common, shared language repository for GRAV
* integration with the language repository
* automatic detection of new variables after upgrades of GRAV, plugins and themes (you can nevertheless always re-index manually)
* Bit of code cleanup and less dependencies (bootstrap 3, datatables-net)
* Mark translations as private allowing for alternative public value

## Installation

Installing the Babel plugin can be done in three ways. Once approved for GRAV, the GPM (Grav Package Manager) installation method enables you to quickly and easily install the plugin with a simple terminal command, while the manual method enables you to do so via a zip file.

If you install manually please assure yourself that the plugin folder inside /user/plugins directory is named babel.

The third method is through the Administration Panel.

### Administration Panel Installation (Preferred)

The simplest way to install this plugin is via the [Grav Admininitration Panel](https://learn.getgrav.org/admin-panel/plugins). Once inside of the Plugins section click ADD and select the 
Babel for installation.


### GPM Installation

Another simple way to install this plugin is via the [Grav Package Manager (GPM)](http://learn.getgrav.org/advanced/grav-gpm) through your system's terminal (also called the command line).  From the root of your Grav instance type:

    bin/gpm install babel

This will install the Babel plugin into your `/user/plugins` directory within Grav. Its files can be found under `/your/site/grav/user/plugins/babel`.

## Help

Help can be obtained via the [issue section](https://github.com/leotiger/grav-plugin-babel/issues) but we cannot guarantee to answer in a timely or exhaustive fashion.

## Credits

Thanks Trilby Media for [TNTSearch](https://github.com/trilbymedia/grav-plugin-tntsearch). This plugin uses modified code portions.


