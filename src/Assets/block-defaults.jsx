//------------------------------------------------------------------------
// Threespot default-block custom fields
//
// Side-effect module: import once from the theme's block-defaults.jsx
// entry to add Threespot's standard custom attributes, sidebar controls,
// and editor/frontend class output for core blocks. The theme then layers
// on its own site-specific filters in the same file.
//
// Currently adds:
//  - "Force line break" toggle + top/bottom margin and "Text wrapping"
//    selectors to heading blocks
//  - "Text wrapping" selector to pullquote blocks
//  - "Hide Bullets" toggle to list blocks
//  - "Sticky" toggle to column blocks
//
// https://developer.wordpress.org/block-editor/reference-guides/filters/block-filters/
//------------------------------------------------------------------------
const { addFilter } = wp.hooks;
const { createHigherOrderComponent } = wp.compose;
const { Fragment } = wp.element;
const { InspectorControls } = wp.blockEditor;
const {
  PanelBody,
  ToggleControl,
  SelectControl,
  __experimentalToggleGroupControl: ToggleGroupControl,
  __experimentalToggleGroupControlOption: ToggleGroupControlOption,
} = wp.components;

const ACCORDION_BLOCK = 'core/accordion';
const COLUMN_BLOCK = 'core/column';
const DETAILS_BLOCK = 'core/details';
const GALLERY_BLOCK = 'core/gallery';
const HEADING_BLOCK = 'core/heading';
const LIST_BLOCK = 'core/list';
const PULLQUOTE_BLOCK = 'core/pullquote';

// Shared "Text Wrapping" pieces, reused by every block that opts into it.
const TEXT_WRAP_ATTRIBUTE = { textWrap: { type: 'string', default: 'default' } }; // default, pretty, balance
const TEXT_WRAP_OPTIONS = [
  { label: 'Default', value: 'default' },
  { label: 'Avoid orphans', value: 'pretty' },
  { label: 'Balance lines', value: 'balance' },
];
const PULLQUOTE_TEXT_WRAP_OPTIONS = [
  { label: 'Default', value: 'default' },
  { label: 'Balance lines', value: 'balance' },
];

// Reusable "Text Wrapping" select control. Defaults to the full option set;
// pass `options` to offer a subset (e.g. pullquotes).
function TextWrapControl({ value, onChange, options = TEXT_WRAP_OPTIONS }) {
  return (
    <SelectControl
      label="Text Wrapping"
      value={value}
      options={options}
      onChange={onChange}
    />
  );
}

// Returns the class fragment for a textWrap value, or '' for the default.
function textWrapClass(textWrap) {
  return textWrap && textWrap !== 'default' ? ` text-wrap-${textWrap}` : '';
}

// Shared margin pieces, reused by top/bottom margin selectors.
// `short` is the abbreviated label shown in the segmented control (the
// inspector sidebar is ~248px, too narrow for five full words); `label`
// is the full word, kept as the option's aria-label for assistive tech.
const MARGIN_ATTRIBUTE = { type: 'string', default: 'default' }; // default, small, medium, large, none
const MARGIN_OPTIONS = [
  { label: 'Auto', short: 'Auto', value: 'default' },
  { label: 'Small', short: 'Small', value: 'small' },
  { label: 'Medium', short: 'Med', value: 'medium' },
  { label: 'Large', short: 'Large', value: 'large' },
  { label: 'None', short: 'None', value: 'none' },
];

// Reusable margin control: a segmented button group so authors can switch
// presets in a single click. Values map to the same classes as before via
// marginClass(), so editor/frontend output is unchanged.
function MarginControl({ label, value, onChange }) {
  return (
    <ToggleGroupControl
      label={label}
      value={value}
      onChange={onChange}
      isBlock
      __nextHasNoMarginBottom
    >
      {MARGIN_OPTIONS.map((option) => (
        <ToggleGroupControlOption
          key={option.value}
          value={option.value}
          label={option.short}
          aria-label={option.label}
        />
      ))}
    </ToggleGroupControl>
  );
}

// Returns a `${prefix}-${value}` class fragment, or '' for the default.
function marginClass(prefix, value) {
  return value && value !== 'default' ? ` ${prefix}-${value}` : '';
}

// 1. Add new attributes to specific blocks
function addCustomBlockAttributes(settings, name) {
  if (name === HEADING_BLOCK) {
    return {
      ...settings,
      attributes: {
        ...settings.attributes,
        ...TEXT_WRAP_ATTRIBUTE,
        bottomMargin: MARGIN_ATTRIBUTE,
        topMargin: MARGIN_ATTRIBUTE,
        forceLineBreak: { type: 'boolean', default: false },
      },
      supports: {
        ...settings.supports,
        align: false, // Disable alignment options
      },
    };
  }

  if (name === PULLQUOTE_BLOCK) {
    return {
      ...settings,
      attributes: {
        ...settings.attributes,
        ...TEXT_WRAP_ATTRIBUTE,
      },
    };
  }

  if (name === LIST_BLOCK) {
    return {
      ...settings,
      attributes: {
        ...settings.attributes,
        hideBullets: { type: 'boolean', default: false },
      },
      supports: {
        ...settings.supports,
        align: ['wide', 'full'], // Enable wide and full alignment
      },
    };
  }

  if (name === GALLERY_BLOCK) {
    return {
      ...settings,
      supports: {
        ...settings.supports,
        align: ['wide', 'full'], // remove left/center/right alignment, keep wide/full
      },
    };
  }

  if (name === DETAILS_BLOCK || name === ACCORDION_BLOCK) {
    return {
      ...settings,
      supports: {
        ...settings.supports,
        align: ['wide'], // remove "Full width" alignment
      },
    };
  }

  if (name === COLUMN_BLOCK) {
    return {
      ...settings,
      attributes: {
        ...settings.attributes,
        isSticky: {
          type: 'boolean',
          default: false
        }
      }
    };
  }

  return settings;
}
addFilter('blocks.registerBlockType', 'threespot/custom-block-attrs', addCustomBlockAttributes);

// 2. Add sidebar controls
const withCustomBlockControls = createHigherOrderComponent((BlockEdit) => {
  return (props) => {
    if (props.name === HEADING_BLOCK) {
      const { attributes, setAttributes } = props;
      const { textWrap, bottomMargin, topMargin, forceLineBreak } = attributes;

      return (
        <Fragment>
          <InspectorControls>
            <PanelBody title="Text Options" initialOpen={true}>
              <MarginControl
                label="Top Margin"
                value={topMargin}
                onChange={(value) => setAttributes({ topMargin: value })}
              />
              <MarginControl
                label="Bottom Margin"
                value={bottomMargin}
                onChange={(value) => setAttributes({ bottomMargin: value })}
              />
              <TextWrapControl
                value={textWrap}
                onChange={(value) => setAttributes({ textWrap: value })}
              />
              <ToggleControl
                label="Force line break after left/right aligned blocks."
                checked={!!forceLineBreak}
                onChange={(value) => setAttributes({ forceLineBreak: value })}
              />
            </PanelBody>
          </InspectorControls>
          <BlockEdit {...props} />
        </Fragment>
      );
    }

    if (props.name === PULLQUOTE_BLOCK) {
      const { attributes, setAttributes } = props;
      const { textWrap } = attributes;

      return (
        <Fragment>
          <InspectorControls>
            <PanelBody title="Text Options" initialOpen={true}>
              <TextWrapControl
                value={textWrap}
                options={PULLQUOTE_TEXT_WRAP_OPTIONS}
                onChange={(value) => setAttributes({ textWrap: value })}
              />
            </PanelBody>
          </InspectorControls>
          <BlockEdit {...props} />
        </Fragment>
      );
    }

    if (props.name === LIST_BLOCK) {
      const { attributes, setAttributes } = props;
      const { hideBullets } = attributes;

      return (
        <Fragment>
          <InspectorControls>
            <PanelBody title="List Options" initialOpen={true}>
              <ToggleControl
                label="Hide Bullets"
                checked={!!hideBullets}
                onChange={(value) => setAttributes({ hideBullets: value })}
              />
            </PanelBody>
          </InspectorControls>
          <BlockEdit {...props} />
        </Fragment>
      );
    }

    if (props.name === COLUMN_BLOCK) {
      const { attributes, setAttributes } = props;
      const { isSticky } = attributes;

      return (
        <Fragment>
          <InspectorControls>
            <PanelBody title="Custom Options" initialOpen={true}>
              <ToggleControl
                label="Sticky"
                checked={!!isSticky}
                onChange={(value) => setAttributes({ isSticky: value })}
                help="Make this column sticky on scroll"
              />
            </PanelBody>
          </InspectorControls>
          <BlockEdit {...props} />
        </Fragment>
      );
    }

    if (props.name === GALLERY_BLOCK) {
      const isLogos = (props.attributes.className || '').includes('is-style-logos');

      return (
        <Fragment>
          {isLogos && (
            <InspectorControls>
              {/* The Columns control is meaningless for the Logos style, which
                  uses a fixed flow layout. InspectorControls only mounts for the
                  selected block, so this style is scoped to a selected logos
                  gallery and never affects other galleries. */}
              <style>{`
                .components-tools-panel-item:has([aria-label="Columns"][type="range"]) {
                  display: none;
                }
              `}</style>
            </InspectorControls>
          )}
          <BlockEdit {...props} />
        </Fragment>
      );
    }

    return <BlockEdit {...props} />;
  };
}, 'withCustomBlockControls');
addFilter('editor.BlockEdit', 'threespot/custom-block-controls', withCustomBlockControls);

// 3. Add classes to the block root in the editor
const withCustomBlockClasses = createHigherOrderComponent((BlockListBlock) => {
  return (props) => {
    let newClassName = props.className || '';

    if (props.name === HEADING_BLOCK) {
      const { textWrap, bottomMargin, topMargin, forceLineBreak } = props.attributes;
      newClassName += textWrapClass(textWrap);
      newClassName += marginClass('top-margin', topMargin);
      newClassName += marginClass('bottom-margin', bottomMargin);
      if (forceLineBreak) {
        newClassName += ' clear-both';
      }
    }
    if (props.name === PULLQUOTE_BLOCK) {
      newClassName += textWrapClass(props.attributes.textWrap);
    }
    if (props.name === LIST_BLOCK) {
      const { hideBullets } = props.attributes;
      if (hideBullets) {
        newClassName += ' no-bullets';
      }
    }

    if (props.name === COLUMN_BLOCK) {
      const { isSticky } = props.attributes;

      if (isSticky) {
        newClassName += ' is-sticky';
      }
    }

    return <BlockListBlock {...props} className={newClassName.trim()} />;
  };
}, 'withCustomBlockClasses');
addFilter('editor.BlockListBlock', 'threespot/custom-block-editor-classes', withCustomBlockClasses);

// 4. Apply front-end classes
function applyCustomBlockProps(extraProps, blockType, attributes) {
  let classNames = extraProps.className || '';

  if (blockType.name === HEADING_BLOCK) {
    classNames += textWrapClass(attributes.textWrap);
    classNames += marginClass('top-margin', attributes.topMargin);
    classNames += marginClass('bottom-margin', attributes.bottomMargin);
    if (attributes.forceLineBreak) {
      classNames += ' clear-both';
    }
  }

  if (blockType.name === PULLQUOTE_BLOCK) {
    classNames += textWrapClass(attributes.textWrap);
  }

  if (blockType.name === LIST_BLOCK) {
    if (attributes.hideBullets) {
      classNames += ' no-bullets';
    }
  }

  if (blockType.name === COLUMN_BLOCK) {
    if (attributes.isSticky) {
      classNames += ' is-sticky';
    }
  }

  extraProps.className = classNames.trim();
  return extraProps;
}
addFilter('blocks.getSaveContent.extraProps', 'threespot/custom-block-props', applyCustomBlockProps);
