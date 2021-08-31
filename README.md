# nl.pum.amsco

This CiviCRM-extension can import CSV-data for AMSCO Applications.
It first reads the imported CSV-file, then it:
* creates a CiviCRM contact
* creates the contact data for the contact
* creates a authorised contact linked as relationship to the main contact
* It fill the customer data like yearly information and other data


The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Requirements

* PHP v5.6 (Tested, might work with other versions, but not tested)
* CiviCRM v4.4.8 (Tested, might work with other versions, but not tested)

## Installation (Web UI)

This extension has not yet been published for installation via the web UI.

## Installation (CLI, Zip)

Sysadmins and developers may download the `.zip` file for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
cd <extension-dir>
cv dl nl.pum.amsco@https://github.com/PUMNL/nl.pum.amsco/archive/master.zip
```

## Installation (CLI, Git)

Sysadmins and developers may clone the [Git](https://en.wikipedia.org/wiki/Git) repo for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
git clone https://github.com/PUMNL/nl.pum.amsco.git
cv en amsco
```

## Usage

Cases -> Import Amso Application brings up a screen which can be used to import the csv-file

## Known Issues


