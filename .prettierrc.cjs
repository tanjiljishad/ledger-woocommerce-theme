// @wordpress/prettier-config run through stock prettier@3 (not wp-prettier):
// tabs (width 4), single quotes, es5 trailing commas, 80 columns, semicolons,
// `bracketSpacing` on so object literals get `{ a: 1 }`. The config's
// `parenSpacing` option is a wp-prettier extension that stock prettier ignores,
// so calls and params format as `fn(a)`, not `fn( a )`. See ADR 0007.
module.exports = require( '@wordpress/prettier-config' );
