<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'simonrichardson');

/** MySQL database username */
define('DB_USER', 'jonomilnes');

/** MySQL database password */
define('DB_PASSWORD', 'swimming1');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'E4,L2O72A:.ewRmhRjfR{y9O1+r%+#DCfba]f:)z`oRc_^j1q.+`=xW$zLl<K7B1');
define('SECURE_AUTH_KEY',  '{}dq4LU|J{C{5j>VbJ;PaBaAY3>)601tHdrp|>7e}z9B)J$zd0l/C5Ex<td7^xm-');
define('LOGGED_IN_KEY',    'zXZcSH(r ^0URwA4ECoIB6PJ%[`?EWXE~4.`O19wn+x*Du,%@f=lfpGw ~sqkEd2');
define('NONCE_KEY',        'V,)f/YPogc(;cbAb5k@{$K}qHvqf7;pVtitrM00_V]{YjjSr8S/Wh -#$eVOl`,q');
define('AUTH_SALT',        '8d0@YIM&s#KI[Idc%}y7d+e8D,kanDzHj6*o|?qybmV1qBwR=$YQPQh40C1F^)oe');
define('SECURE_AUTH_SALT', '5n_>.|zA66XX;<uPru_4<|J~Mt53 il|- 1JsThX!Gvif0D>&0Sz5MIM@?QViZ}W');
define('LOGGED_IN_SALT',   '{6@;E5-,/w}T2jl:0He_ 8v0<OM/:]4h #?g*3!+_gaGjXJ!VH.9HUUI.!axigcF');
define('NONCE_SALT',       '5&fz^8ELGw7KsHw*EO8u%/_W~[F)2cHEIig|K?fjQCr%zS--zq.)X}>.d!vNPg<?');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
