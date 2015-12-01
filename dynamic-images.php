<?php
/*
 * Plugin Name: Dynamic Images
 * Plugin URI: http://phenomblue.com/
 * Description: Adds support for dynamic images.
 * Author: Phenomblue
 * Version: 1.0
 * Author URI: http://www.phenomblue.com/
 *
 * -------------------------------------
 *
 * @package Dynamic Images
 * @category Plugin
 * @author Jacob Dunn
 * @link http://www.phenomblue.com/ Phenomblue
 * @version 1.0
 *
 * -------------------------------------
 * 
 * For Further information, see http://www.w3.org/community/respimg/
 *
 * -------------------------------------
 *
 * Dynamic Images is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

define('DI_ROOT', dirname(__FILE__));

foreach (glob(DI_ROOT.'/inc/*.php') as $filename)
	require_once($filename);