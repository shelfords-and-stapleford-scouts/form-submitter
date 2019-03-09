<?php
/*
+----------------------------------------------------------------------
| Copyright (c) 2018 Genome Research Ltd.
| This is part of the Wellcome Sanger Institute extensions to
| wordpress.
+----------------------------------------------------------------------
| This extension to Worpdress is free software: you can redistribute
| it and/or modify it under the terms of the GNU Lesser General Public
| License as published by the Free Software Foundation; either version
| 3 of the License, or (at your option) any later version.
|
| This program is distributed in the hope that it will be useful, but
| WITHOUT ANY WARRANTY; without even the implied warranty of
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
| Lesser General Public License for more details.
|
| You should have received a copy of the GNU Lesser General Public
| License along with this program. If not, see:
|     <http://www.gnu.org/licenses/>.
+----------------------------------------------------------------------

# Support functions to define complex forms in YAML and collect data outside
# wordpress
#
# See foot of file for documentation on use...
#
# Author         : js5
# Maintainer     : js5
# Created        : 2018-02-09
# Last modified  : 2018-02-12

 * @package   FormSubmitter
 * @author    James Smith james@jamessmith.me.uk
 * @license   GLPL-3.0+
 * @link      https://jamessmith.me.uk/form-submitter/
 * @copyright 2018 James Smith
 *
 * @wordpress-plugin
 * Plugin Name: Form Submitter
 * Plugin URI:  https://jamessmith.me.uk/form-submitter/
 * Description: Support functions to apply simple templates to acf pro data structures!
 * Version:     0.0.1
 * Author:      James Smith
 * Author URI:  https://jamessmith.me.uk
 * Text Domain: base-theme-class-locale
 * License:     GNU Lesser General Public v3
 * License URI: https://www.gnu.org/licenses/lgpl.txt
 * Domain Path: /lang
 */
 
  require_once plugin_dir_path( __FILE__ ).'class-form-submitter.php';
  
  (new FormSubmitter())->register_short_code();
  
