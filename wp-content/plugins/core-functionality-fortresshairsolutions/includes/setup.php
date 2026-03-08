<?php

/**
 * Setup functions
 *
 * @package     Osim\CoreFunctionality
 * @since       1.0.0
 * @author      thartl
 * @license     GNU General Public License 2.0+
 */

namespace Osim\CoreFunctionality;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


add_filter( 'th_password_strength_override', __NAMESPACE__ . '\set_password_strength_override' );
/**
 * Set required user account password strength. Hooks into Password Security plugin.
 *
 * @return int
 */
function set_password_strength_override() {
	return 2;
}
