import js from '@eslint/js';
import globals from 'globals';

export default [
  js.configs.recommended,
  {
    ignores: [
      '**/resources/scripts/admin/**/*',
      '**/resources/scripts/lib/**/*'
    ],
  },
  {
    files: ['**/*.js', '**/*.jsx', '**/*.mjs', '**/*.cjs'],
    languageOptions: {
      ecmaVersion: 2020,
      sourceType: 'module',
      globals: {
        ...globals.node,
        ...globals.browser,
        ...globals.amd,
        ...globals.jquery,
        wp: 'readonly',
      },
      parserOptions: {
        ecmaFeatures: {
          // Allow JSX syntax in .js files (used with Gutenberg wp.element).
          // ESLint 9+ counts JSX identifiers as references on its own, so
          // no-unused-vars needs no eslint-plugin-react to see them.
          jsx: true,
        },
      },
    },
    rules: {
      'no-console': 0,
      'quotes': ['warn', 'single', { avoidEscape: true }],
      'comma-dangle': [
        'error',
        {
          'arrays': 'ignore',
          'objects': 'ignore',
          'imports': 'ignore',
          'exports': 'ignore',
          'functions': 'ignore',
        },
      ],
    },
  },
];
