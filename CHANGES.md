### 2025.05.12 ###

* MDLSITE-7990 Fixes to make the styling work with Moodle 5.0. Credit goes to Daniel
  Ureña.

### 2024.08.05 ###

* MDLSITE-6660 the "Substring" filter now allows to specify whether it applies to
  translated strings, the English originals, or both.

### 2023.02.05 ###

* The MOV command behaviour changed so that it is technically an alias for the CPY
  now. This is to prevent situations when the MOV was used in the commit message even
  without the string being actually deleted from the original.

### 3.6.3 ###

* Currently supported versions are highlighted in green.
* Clicking the "Moodle App" button below the component selector sets the filter so
  that only the strings used by the mobile app are selected for translation.
* A new option "latest available version" can be used instead of selecting particular
  moodle versions. When selected, the filter picks the most recent version for each of
  the selected component.
* A new filtering option "only strings used in the Moodle App" is available.
* Strings used in the Mobile App have a little phone icon displayed.

Credit goes to Pau Ferrer for contributing these improvements.

### 3.6.2 ###

* Added support for registering the Moodle for Workplace strings

### 3.6.1 ###

* Added support for displaying translation stats at the Moodle plugins directory
