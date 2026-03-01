/**
 * Config
 * -------------------------------------------------------------------------------------
 * ! IMPORTANT: Make sure you clear the browser local storage In order to see the config changes in the template.
 * ! To clear local storage: (https://www.leadshook.com/help/how-to-clear-local-storage-in-google-chrome-browser/).
 */

'use strict';
/* JS global variables
 !Please use the hex color code (#000) here. Don't use rgba(), hsl(), etc
*/
window.config = {
  // global color variables for charts except chartjs
  colors: {
    primary: window.Helpers.getCssVar('primary'),
    secondary: window.Helpers.getCssVar('secondary'),
    success: window.Helpers.getCssVar('success'),
    info: window.Helpers.getCssVar('info'),
    warning: window.Helpers.getCssVar('warning'),
    danger: window.Helpers.getCssVar('danger'),
    dark: window.Helpers.getCssVar('dark'),
    black: window.Helpers.getCssVar('pure-black'),
    white: window.Helpers.getCssVar('white'),
    cardColor: window.Helpers.getCssVar('paper-bg'),
    bodyBg: window.Helpers.getCssVar('body-bg'),
    bodyColor: window.Helpers.getCssVar('body-color'),
    headingColor: window.Helpers.getCssVar('heading-color'),
    textMuted: window.Helpers.getCssVar('secondary-color'),
    borderColor: window.Helpers.getCssVar('border-color')
  },
  colors_label: {
    primary: window.Helpers.getCssVar('primary-bg-subtle'),
    secondary: window.Helpers.getCssVar('secondary-bg-subtle'),
    success: window.Helpers.getCssVar('success-bg-subtle'),
    info: window.Helpers.getCssVar('info-bg-subtle'),
    warning: window.Helpers.getCssVar('warning-bg-subtle'),
    danger: window.Helpers.getCssVar('danger-bg-subtle'),
    dark: window.Helpers.getCssVar('dark-bg-subtle')
  },
  fontFamily: window.Helpers.getCssVar('font-family-base'),
  enableMenuLocalStorage: true // Enable menu state with local storage support
};

window.assetsPath = document.documentElement.getAttribute('data-assets-path');
window.templateName = document.documentElement.getAttribute('data-template');

/**
 * TemplateCustomizer
 * ! You must use(include) template-customizer.js to use TemplateCustomizer settings
 * -----------------------------------------------------------------------------------------------
 */

/**
 * TemplateCustomizer settings
 * -------------------------------------------------------------------------------------
 * displayCustomizer: true(Show customizer), false(Hide customizer)
 * lang: To set default language, Add more languages and set default. Fallback language is 'en'
 * defaultPrimaryColor: '#7367F0' | Set default primary color
 * defaultSkin: 0(Default), 1(Bordered)
 * defaultTheme: 'light', 'dark', 'system'
 * defaultSemiDark: true, false (For dark menu only)
 * defaultContentLayout: 'compact', 'wide' (compact=container-xxl, wide=container-fluid)
 * defaultHeaderType: 'static', 'fixed' (for horizontal layout only)
 * defaultMenuCollapsed: true, false (For vertical layout only)
 * defaultNavbarType: 'sticky', 'static', 'hidden' (For vertical layout only)
 * defaultTextDir: 'ltr', 'rtl' (Direction)
 * defaultFooterFixed: true, false (For vertical layout only)
 * defaultShowDropdownOnHover : true, false (for horizontal layout only)
 * controls: [ 'color', 'theme', 'skins', 'semiDark', 'layoutCollapsed', 'layoutNavbarOptions', 'headerType', 'contentLayout', 'rtl' ] | Show/Hide customizer controls
 */

if (typeof TemplateCustomizer !== 'undefined') {
  var CUSTOMIZER_STORAGE_PREFIX = 'templateCustomizer-' + templateName + '--';
  var ADMIN_LANGUAGE_KEY = 'wiseplay-admin-language';

  TemplateCustomizer.LANGUAGES.pt = {
    panel_header: 'Personalizar tema',
    panel_sub_header: 'Personalize e visualize em tempo real',
    theming_header: 'Tema',
    color_label: 'Cor primaria',
    theme_label: 'Tema',
    skin_label: 'Estilo',
    semiDark_label: 'Semi Dark',
    layout_header: 'Layout',
    layout_label: 'Menu (Navigation)',
    layout_header_label: 'Header types',
    style_switch_light: 'Claro',
    style_switch_dark: 'Escuro',
    content_label: 'Conteudo',
    layout_static: 'Estatico',
    layout_offcanvas: 'Offcanvas',
    layout_fixed: 'Fixo',
    layout_fixed_offcanvas: 'Fixo offcanvas',
    layout_dd_open_label: 'Abrir dropdown ao passar o mouse',
    layout_navbar_label: 'Navbar type',
    layout_footer_label: 'Footer fixo',
    misc_header: 'Opcoes',
    direction_label: 'Direcao'
  };

  function resolveCustomizerLanguage() {
    var storedAdminLanguage = localStorage.getItem(ADMIN_LANGUAGE_KEY) || document.documentElement.getAttribute('lang') || 'en-US';
    return String(storedAdminLanguage).toLowerCase().startsWith('pt') ? 'pt' : 'en';
  }

  function translatedOptions(options, translations) {
    return options.map(function (item) {
      return Object.assign({}, item, {
        title: translations[item.name] || item.title
      });
    });
  }

  function buildCustomizerOptions(lang) {
    if (lang !== 'pt') {
      return {
        availableColors: TemplateCustomizer.COLORS,
        availableSkins: TemplateCustomizer.SKINS,
        availableThemes: TemplateCustomizer.THEMES,
        availableContentLayouts: TemplateCustomizer.CONTENT,
        availableHeaderTypes: TemplateCustomizer.HEADER_TYPES,
        availableMenuCollapsed: TemplateCustomizer.LAYOUTS,
        availableNavbarOptions: TemplateCustomizer.NAVBAR_OPTIONS,
        availableDirections: TemplateCustomizer.DIRECTIONS
      };
    }

    return {
      availableColors: translatedOptions(TemplateCustomizer.COLORS, {
        primary: 'Primaria',
        secondary: 'Secundaria',
        success: 'Sucesso',
        info: 'Informacao',
        warning: 'Aviso',
        danger: 'Perigo'
      }),
      availableSkins: translatedOptions(TemplateCustomizer.SKINS, {
        default: 'Default',
        bordered: 'Bordered'
      }),
      availableThemes: translatedOptions(TemplateCustomizer.THEMES, {
        light: 'Claro',
        dark: 'Escuro',
        system: 'Sistema'
      }),
      availableContentLayouts: translatedOptions(TemplateCustomizer.CONTENT, {
        compact: 'Compacto',
        wide: 'Amplo'
      }),
      availableHeaderTypes: translatedOptions(TemplateCustomizer.HEADER_TYPES, {
        fixed: 'Fixo',
        static: 'Estatico'
      }),
      availableMenuCollapsed: translatedOptions(TemplateCustomizer.LAYOUTS, {
        expanded: 'Expandido',
        collapsed: 'Colapsado'
      }),
      availableNavbarOptions: translatedOptions(TemplateCustomizer.NAVBAR_OPTIONS, {
        sticky: 'Fixa',
        static: 'Estatica',
        hidden: 'Oculta'
      }),
      availableDirections: translatedOptions(TemplateCustomizer.DIRECTIONS, {
        ltr: 'Esquerda para direita',
        rtl: 'Direita para esquerda'
      })
    };
  }

  window.WisePlayAdminCustomizer = {
    boot: function () {
      var lang = resolveCustomizerLanguage();
      var optionSet = buildCustomizerOptions(lang);

      localStorage.setItem(CUSTOMIZER_STORAGE_PREFIX + 'Lang', lang);
      if (lang === 'pt') {
        localStorage.removeItem(CUSTOMIZER_STORAGE_PREFIX + 'LangAr');
      }

      if (window.templateCustomizer && typeof window.templateCustomizer.destroy === 'function') {
        window.templateCustomizer.destroy();
      }
      document.querySelectorAll('.template-customizer').forEach(function (node) {
        node.parentNode && node.parentNode.removeChild(node);
      });

      window.templateCustomizer = new TemplateCustomizer({
        displayCustomizer: true,
        lang: lang,
        availableColors: optionSet.availableColors,
        availableSkins: optionSet.availableSkins,
        availableThemes: optionSet.availableThemes,
        availableContentLayouts: optionSet.availableContentLayouts,
        availableHeaderTypes: optionSet.availableHeaderTypes,
        availableMenuCollapsed: optionSet.availableMenuCollapsed,
        availableNavbarOptions: optionSet.availableNavbarOptions,
        availableDirections: optionSet.availableDirections,
        // defaultPrimaryColor: '#D11BB4',
        // defaultSkin: 1,
        // defaultTheme: 'system',
        // defaultSemiDark: true,
        // defaultContentLayout: 'wide',
        // defaultHeaderType: 'static',
        // defaultMenuCollapsed: true,
        // defaultNavbarType: 'static',
        // defaultTextDir: 'rtl',
        // defaultFooterFixed: false,
        // defaultShowDropdownOnHover: false,
        controls: [
          'color',
          'theme',
          'skins',
          'semiDark',
          'layoutCollapsed',
          'layoutNavbarOptions',
          'headerType',
          'contentLayout',
          'rtl'
        ]
      });

      if (window.templateCustomizer && typeof window.templateCustomizer.setLang === 'function') {
        window.templateCustomizer.setLang(lang, true, true);
      }
    }
  };

  window.WisePlayAdminCustomizer.boot();
}
