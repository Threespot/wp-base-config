//------------------------------------------------------------------------
// Threespot block-editor defaults
//
// Side-effect module: import once from the theme's gutenberg.js entry to
// apply Threespot's standard block-editor customizations. The theme then
// appends its own registerBlockStyle()/registerBlockVariation() calls.
//
// https://developer.wordpress.org/block-editor/developers/filters/block-filters/
//------------------------------------------------------------------------
wp.domReady(() => {
  // Block config that can’t be done via theme.json
  wp.blocks.getBlockTypes().forEach((blockType) => {
    // SearchWP adds a block that can’t be removed with the Block Manager
    //  plugin so we have to manually unregister it.
    if (blockType.category == 'searchwp') {
      wp.blocks.unregisterBlockType(blockType.name);
    }
  });

  // Add custom classes to top-level wrappers of default blocks
  // https://developer.wordpress.org/block-editor/reference-guides/filters/block-filters/#blocks-getblockdefaultclassname
  // https://poolghost.com/rename-class-names-in-gutenberg-blocks/
  wp.hooks.addFilter(
    'blocks.getBlockDefaultClassName',
    'threespot/set-block-custom-class-name',
    (className, blockName) => {
      // Add "u-richtext" class to blocks that support inner blocks
      // Note: If you need to add "u-richtext" to a child element,
      //       (e.g. Media & Text, Cover) that can be done via PHP in
      //       `customizeBlockMarkup()` in /src/MuPlugins/BlockConfig.php
      const richtextBlocks = [
        'core/column',
        'core/details',
        'core/group',
      ];

      if (richtextBlocks.includes(blockName)) {
        return className + ' u-richtext';
      }

      return className;
    }
  );

  // Register custom styles
  wp.blocks.registerBlockStyle('core/button', {
    name: 'link',
    label: 'Link',
  });

  wp.blocks.registerBlockStyle('core/columns', {
    name: 'no-col-gap',
    label: 'No Gutter',
  });

  wp.blocks.registerBlockStyle('core/gallery', {
    name: 'logos-centered',
    label: 'Logos',
  });

  wp.blocks.registerBlockStyle('core/gallery', {
    name: 'logos-grid',
    label: 'Logo Grid',
  });

  wp.blocks.registerBlockStyle('core/group', {
    name: 'no-vert-margin',
    label: 'No Margin',
  });

  wp.blocks.registerBlockStyle('core/heading', {
    name: 'h2',
    label: 'H2',
  });

  wp.blocks.registerBlockStyle('core/heading', {
    name: 'h3',
    label: 'H3',
  });

  wp.blocks.registerBlockStyle('core/heading', {
    name: 'h4',
    label: 'H4',
  });

  wp.blocks.registerBlockStyle('core/heading', {
    name: 'h5',
    label: 'H5',
  });

  wp.blocks.registerBlockStyle('core/heading', {
    name: 'h6',
    label: 'H6',
  });

  wp.blocks.registerBlockStyle('core/image', {
    name: 'max-height',
    label: 'Max Height',
  });

  wp.blocks.registerBlockStyle('core/list', {
    name: 'col-2',
    label: '2 Columns',
  });

  wp.blocks.registerBlockStyle('core/list', {
    name: 'col-3',
    label: '3 Columns',
  });

  wp.blocks.registerBlockStyle('core/list', {
    name: 'col-4',
    label: '4 Columns',
  });

  wp.blocks.registerBlockStyle('core/paragraph', {
    name: 'large',
    label: 'Large',
  });

  //------------------------------------------------------------------------
  // Configure TinyMCE
  //------------------------------------------------------------------------
  /* global acf */
  if (typeof acf !== 'undefined') {
    acf.add_filter('wysiwyg_tinymce_settings', function(mceInit) {
      // Strip Gutenberg block magic comments to avoid breaking ACF wysiwyg
      // fields when copying and pasting block text. The curly braces in
      // e.g. <!-- wp:heading {"level":3} --> otherwise break ACF wysiwyg.
      // https://www.advancedcustomfields.com/resources/javascript-api/#filters-wysiwyg_tinymce_settings
      // https://www.tiny.cloud/docs/plugins/paste/#paste_preprocess
      // RegEx from https://stackoverflow.com/a/29194283/673457
      mceInit.paste_preprocess = function(plugin, args) {
        const pattern = /(?=<!--)([\s\S]*?)-->(\n\n+)?/g;
        args.content = args.content.replace(pattern, '');
      };

      // Remove H1 from list of headings
      mceInit.block_formats = 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6;Preformatted=pre';

      // Hide menu bar (i.e. File, Edit, View, Format)
      mceInit.menubar = false;

      return mceInit;
    });
  }
});
