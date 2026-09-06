/**
 * Root ESLint config. `pnpm run lint` fails on any warning (--max-warnings 0)
 * so warnings are treated as errors in CI without muddying editor output.
 */
module.exports = {
  root: true,
  extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
  parserOptions: {
    ecmaVersion: 2022,
    sourceType: 'module',
  },
  env: {
    browser: true,
    node: true,
    es2022: true,
  },
  ignorePatterns: [
    'node_modules/',
    'vendor/',
    'dist/',
    'build/',
    '**/*.min.js',
    'packages/tokens/dist/',
    'packages/tokens/test/__fixtures__/',
  ],
  overrides: [
    {
      files: [ '*.ts', '*.tsx' ],
      parser: '@typescript-eslint/parser',
      parserOptions: { project: [ './tsconfig.json' ] },
    },
    {
      files: [ 'tests/e2e/**/*.ts', '**/*.spec.ts' ],
      rules: {
        'no-console': 'off',
      },
    },
    {
      files: [ '**/*.mjs', 'tools/**/*.js' ],
      env: { node: true },
      rules: {
        'no-console': 'off',
      },
    },
  ],
};
