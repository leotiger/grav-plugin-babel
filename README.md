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

To use the plugin you have to enable language support in your GRAV instance and you have to configure as well the languages you want to
handle with Babel in the configuration of the Babel plugin. The languages handled by Babel do not have to be identical with the languages enabled for your
instance. This allows you to prepare additional languages before activating new ones for your site.

You can access Babel using the dedicated tray icon or by using the link in the plugin configuration. If you use Babel frequently you may activate a permanent Admin menu link
to Babel.

On first time load of the Babel administration page, you will be asked to create an index. Once the index is created, you will see a breakdown
table informing about the general status of translated and untranslated variables and you may filter this data using the domain filter selector.

If there are defined, translated or untranslated items in a given context (language, domain) available you can load the translations using the 
blue buttons in the corresponding rows of the breakdown table.

Once you've loaded a list of language variables you can edit translations for a given language. Inside of the textarea you can use Ctrl+s or
Command+s to save your edited translation. If the translation is empty you can copy existing translations in other languages into the empty field by 
clicking on the language shortcode in the right column next to the translation editor.

To load the changed translations in your current instance, you have to merge them using the Merge button.

If you load a language domain via a selection option of the filter, e.g. PLUGIN_BABEL or THEME_TRACKED you are presented with an additional option
in the translation breakdown table: the export button. The export button allows you to export and download translation packages. If you don't see 
your translation package in the bottom region of the page after running an export, please reload the page.

To reset the system, you can use the Reset button. The reset will remove the Babel edit flag and recover the original definitions. You have to confirm this
operation with a new merge. The merge routine, if it does not detect entries changed with Babel, removes existing merge files from the instance.


## What's missing

* a common, shared language repository for GRAV, Babel will probably integrate this using the Crowdin platform, already used for GRAV and the GRAV
Admin plugin
* integration with the language repository
* automatic detection of new variables after upgrades of GRAV, plugins and themes (you can nevertheless always re-index manually)
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


