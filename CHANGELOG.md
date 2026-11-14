# Changelog

All notable changes to cjw_tmv from 0.1.5 on. Earlier releases are described on
<https://github.com/se7enxweb/cjw_tmv/releases>.

## 0.1.5 (2026-11-14)

- Updated: Pull request #1 (Marco-JAC) is merged: the TMV import (`classes/cjwtmvimporter.php`) escapes the copyright
  of an image caption and the contact and venue lines of an event with
  `htmlspecialchars( ..., ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 )`, the default of PHP 8.1 and later. Without
  flags (including `array_map( 'htmlspecialchars', ... )`), PHP 8.0 used `ENT_COMPAT | ENT_HTML401`: a single quote
  stayed as it is and a line with invalid UTF-8 became empty. The output on PHP 8.1 and later does not change.
- No escaping call without flags (callbacks included) is left in the extension; `php -l` passes on PHP 8.0 and 8.5
  for every PHP file.
- Updated: `ezinfo.php` and `extension.xml` state 0.1.5.
