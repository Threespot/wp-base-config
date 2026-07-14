import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

/**
 * Threespot's shared Vite config.
 *
 * Takes the npm-package plugins as arguments instead of importing them
 * here. Reason: this file is loaded through a symlink from the theme,
 * and Vite's config-loader bundler (Rolldown in Vite 8+) walks the
 * symlink to vendor/ before any of Vite's resolve options apply. Bare
 * imports here would emit UNRESOLVED_IMPORT warnings on every build.
 *
 * The wrapper in each theme owns the imports, so they resolve through
 * the theme's own node_modules. Base still owns the plugin options.
 *
 * The lightningcss helpers (browserslist, browserslistToTargets, Features)
 * are passed in for the same reason — they drive the CSS minifier config
 * below but must be imported by the theme wrapper, not bare-imported here.
 */
export function threespotViteBase({
  createLogger,
  wordpressPlugin,
  wordpressThemeJson,
  viteStaticCopy,
  createSvgIconsPlugin,
  eslint,
  stylelint,
  browserslist,
  browserslistToTargets,
  Features,
  // Theme-supplied options:
  //   jqueryPath        — path (relative to the theme's resources/ dir) to a
  //                       local jQuery to copy + host, e.g.
  //                       'scripts/lib/jquery-4.0.0.min.js'. Omit to skip jQuery
  //                       hosting (AssetConfig leaves core jQuery in place).
  //   staticCopyTargets — extra vite-plugin-static-copy targets to append, e.g.
  //                       [{ src: 'resources/scripts/lib/foo.js', dest: 'assets' }].
  jqueryPath,
  staticCopyTargets = [],
}) {
  // Suppress vite-plugin-eslint's "LintOnStart is turned on" notice — it
  // just restates the option set right here in this file.
  const customLogger = createLogger();
  const originalWarn = customLogger.warn;
  customLogger.warn = (msg, options) => {
    if (typeof msg === 'string' && msg.includes('LintOnStart is turned on')) {
      return;
    }
    originalWarn(msg, options);
  };

  return {
    customLogger,
    base: '/wp-content/themes/sage/public/build/',
    envDir: resolve(__dirname, '../../../../'),
    plugins: [
      eslint({
        lintOnStart: true,
        cache: false,
        include: ['resources/**/*.{js,jsx}'],
        exclude: ['node_modules/**', 'public/**'],
      }),
      stylelint({
        include: ['resources/**/*.{css,scss}'],
      }),
      viteStaticCopy({
        targets: [
          { src: 'resources/images', dest: 'assets' },
          { src: 'resources/fonts', dest: 'assets' },
          // Optional local jQuery. The theme passes a path relative to its
          // resources/ dir (e.g. 'scripts/lib/jquery-4.0.0.min.js'); we copy it to
          // public/build/assets/resources/<path> — vite-plugin-static-copy preserves
          // the src dir structure under dest. AssetConfig::replaceJqueryIfConfigured()
          // derives the matching URL from the 'threespot/assets/jquery_path' filter.
          ...(jqueryPath
            ? [{ src: `resources/${jqueryPath}`, dest: 'assets' }]
            : []),
          // Extra theme-supplied static-copy targets
          ...staticCopyTargets,
        ],
      }),
      // SVG sprite plugin — requires `import 'virtual:svg-icons-register'` in main.js
      // https://github.com/vbenjs/vite-plugin-svg-icons
      createSvgIconsPlugin({
        iconDirs: [resolve(process.cwd(), 'resources/images/sprite')],
        inject: 'body-first',
        symbolId: 'sprite-[name]',
        // https://svgo.dev/docs/preset-default/#plugins-list
        svgoOptions: {
          plugins: [
            { name: 'cleanupIDs', active: false },
            { name: 'collapseGroups', active: false },
            { name: 'removeUselessDefs', active: false },
            { name: 'removeUselessStrokeAndFill', active: false },
            { name: 'convertShapeToPath', active: false },
          ],
        },
      }),
      wordpressPlugin(),
      // Generates theme.json in public/build/assets based on the Tailwind config
      // and theme.json from the base theme folder.
      wordpressThemeJson({
        disableTailwindColors: true,
        disableTailwindFonts: true,
        disableTailwindFontSizes: true,
        disableTailwindBorderRadius: true,
      }),
    ],
    // Transform JSX using the Gutenberg runtime so JSX works in .js files
    // without an explicit React import. Configured via oxc (Vite 8+).
    oxc: {
      jsx: {
        runtime: 'classic',
        pragma: 'wp.element.createElement',
        pragmaFrag: 'wp.element.Fragment',
      },
    },
    // Mirror the JSX transform on Vite's dep-scanner. Without this,
    // the scanner falls back to the React automatic runtime and emits
    // `import "react/jsx-dev-runtime"` for any .jsx it crawls, which fails
    // to resolve since WP block code expects `wp.element` globals.
    optimizeDeps: {
      rolldownOptions: {
        transform: {
          jsx: {
            runtime: 'classic',
            pragma: 'wp.element.createElement',
            pragmaFrag: 'wp.element.Fragment',
          },
        },
      },
    },
    resolve: {
      alias: {
        '@fonts': '/resources/fonts',
        '@functions': '/resources/styles/functions',
        '@images': '/resources/images',
        '@mixins': '/resources/styles/mixins',
        '@scripts': '/resources/scripts',
        '@styles': '/resources/styles',
        '@vars': '/resources/styles/vars',
        // Assets shipped by the threespot/wp-base-config Composer package
        '@threespot-base-config': resolve(__dirname, '../../../../vendor/threespot/wp-base-config/src/Assets'),
      },
      extensions: ['.mjs', '.js', '.ts', '.jsx', '.json'],
    },
    server: {
      host: '0.0.0.0',
      port: 5173,
      // NOTE: Starting in Vite 8.1 `hmr` is replaced with `ws`
      hmr: {
        protocol: 'wss',
      },
    },
    // Vite 8's CSS minifier is Lightning CSS, which does NOT auto-read the
    // browserslist config, so we feed the theme's targets in here.
    // include: Features.MediaQueries keeps legacy min-width/max-width
    // media-query syntax instead of letting Lightning CSS rewrite it to the
    // newer range syntax (width >= …), for deeper browser support. `include`
    // forces these features to always compile to the legacy form regardless
    // of targets (`exclude` does the opposite). MediaQueries =
    // MediaIntervalSyntax | MediaRangeSyntax | CustomMediaQueries.
    css: {
      lightningcss: {
        targets: browserslistToTargets(browserslist()),
        include: Features.MediaQueries,
      },
    },
  };
}
