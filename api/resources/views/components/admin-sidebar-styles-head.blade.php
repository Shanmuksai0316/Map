{{-- Injected via PanelsRenderHook::STYLES_AFTER so it loads after Filament theme and overrides. --}}
<style>
[class*="fi-panel-"] aside.fi-sidebar,
[class*="fi-panel-"] .fi-sidebar,
[class*="fi-panel-"] nav.fi-sidebar-nav,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-content,
[class*="fi-panel-"] .fi-sidebar-content {
  background: #ffffff !important;
  background-color: #ffffff !important;
}
[class*="fi-panel-"] .fi-sidebar-group-label,
[class*="fi-panel-"] span.fi-sidebar-group-label,
[class*="fi-panel-"] .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar-item-icon {
  color: #2F4F2F !important;
  fill: transparent !important;
}
[class*="fi-panel-"] li.fi-sidebar-item > a.fi-sidebar-item-button {
  display: flex !important;
  width: 100% !important;
}
[class*="fi-panel-"] li.fi-sidebar-item:hover > a.fi-sidebar-item-button,
[class*="fi-panel-"] li.fi-sidebar-item > a.fi-sidebar-item-button:hover,
[class*="fi-panel-"] li.fi-sidebar-item.fi-active > a.fi-sidebar-item-button,
[class*="fi-panel-"] li.fi-sidebar-item.fi-active.fi-sidebar-item-active > a.fi-sidebar-item-button {
  background: radial-gradient(143.2% 143.2% at 30% 30%, #F6C32E 0%, #F0B90B 50%, #D99E00 100%) !important;
}
[class*="fi-panel-"] li.fi-sidebar-item:hover > a.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] li.fi-sidebar-item:hover > a.fi-sidebar-item-button .fi-sidebar-item-icon,
[class*="fi-panel-"] li.fi-sidebar-item > a.fi-sidebar-item-button:hover .fi-sidebar-item-label,
[class*="fi-panel-"] li.fi-sidebar-item > a.fi-sidebar-item-button:hover .fi-sidebar-item-icon,
[class*="fi-panel-"] li.fi-sidebar-item.fi-active > a.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] li.fi-sidebar-item.fi-active > a.fi-sidebar-item-button .fi-sidebar-item-icon,
[class*="fi-panel-"] li.fi-sidebar-item.fi-active.fi-sidebar-item-active > a.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] li.fi-sidebar-item.fi-active.fi-sidebar-item-active > a.fi-sidebar-item-button .fi-sidebar-item-icon {
  color: #2F4F2F !important;
  fill: transparent !important;
}
[class*="fi-panel-"] li.fi-sidebar-item:hover .bg-primary-600,
[class*="fi-panel-"] li.fi-sidebar-item.fi-active .bg-primary-600 {
  background-color: #2f4f2f !important;
}
/* Sidebar numeric indicators (navigation badges) need explicit contrast overrides. */
[class*="fi-panel-"] .fi-sidebar-nav .fi-sidebar-item .fi-badge,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item .fi-badge,
[class*="fi-panel-"] .fi-sidebar-nav .fi-sidebar-item [class*="badge"] {
  background-color: #f1f7f1 !important;
  color: #2F4F2F !important;
  border: 1px solid #c6d9c6 !important;
  border-radius: 0.5rem !important;
  font-weight: 700 !important;
  min-width: 1.75rem !important;
  justify-content: center !important;
}
[class*="fi-panel-"] .fi-sidebar-nav .fi-sidebar-item .fi-badge *,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item .fi-badge *,
[class*="fi-panel-"] .fi-sidebar-nav .fi-sidebar-item [class*="badge"] * {
  color: inherit !important;
}
[class*="fi-panel-"] li.fi-sidebar-item:hover .fi-badge,
[class*="fi-panel-"] li.fi-sidebar-item.fi-active .fi-badge,
[class*="fi-panel-"] li.fi-sidebar-item:hover [class*="badge"],
[class*="fi-panel-"] li.fi-sidebar-item.fi-active [class*="badge"] {
  background-color: #2F4F2F !important;
  color: #ffffff !important;
  border-color: #2F4F2F !important;
}
[class*="fi-panel-"] li.fi-sidebar-item > a.fi-sidebar-item-button:focus-visible {
  outline: none !important;
  box-shadow: 0 0 0 2px rgba(255,255,255,0.5) !important;
  border-radius: 8px !important;
}
/* Typography: main items */
[class*="fi-panel-"] nav.fi-sidebar-nav > ul > li.fi-sidebar-item > a.fi-sidebar-item-button,
[class*="fi-panel-"] nav.fi-sidebar-nav > ul > li.fi-sidebar-item > button.fi-sidebar-item-button {
  font-size: 18px !important;
  font-weight: 700 !important;
  line-height: 1.3 !important;
}
[class*="fi-panel-"] nav.fi-sidebar-nav > ul {
  gap: 16px !important;
}
[class*="fi-panel-"] nav.fi-sidebar-nav > ul > li.fi-sidebar-item > a.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] nav.fi-sidebar-nav > ul > li.fi-sidebar-item > button.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] nav.fi-sidebar-nav > ul > li.fi-sidebar-item > a.fi-sidebar-item-button span,
[class*="fi-panel-"] nav.fi-sidebar-nav > ul > li.fi-sidebar-item > button.fi-sidebar-item-button span {
  font-size: inherit !important;
  font-weight: inherit !important;
}
/* Typography: sub-items */
[class*="fi-panel-"] nav.fi-sidebar-nav ul ul li.fi-sidebar-item > a.fi-sidebar-item-button,
[class*="fi-panel-"] nav.fi-sidebar-nav ul ul li.fi-sidebar-item > button.fi-sidebar-item-button {
  font-size: 16px !important;
  font-weight: 400 !important;
  line-height: 1.3 !important;
}
[class*="fi-panel-"] nav.fi-sidebar-nav ul ul {
  gap: 8px !important;
}
[class*="fi-panel-"] nav.fi-sidebar-nav ul ul li.fi-sidebar-item > a.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] nav.fi-sidebar-nav ul ul li.fi-sidebar-item > button.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] nav.fi-sidebar-nav ul ul li.fi-sidebar-item > a.fi-sidebar-item-button span,
[class*="fi-panel-"] nav.fi-sidebar-nav ul ul li.fi-sidebar-item > button.fi-sidebar-item-button span {
  font-size: inherit !important;
  font-weight: inherit !important;
}
/* Final fallback by nesting depth (panel-agnostic structure) */
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item > a.fi-sidebar-item-button,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item > button.fi-sidebar-item-button {
  font-size: 18px !important;
  font-weight: 700 !important;
}
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item > a.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item > button.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item > a.fi-sidebar-item-button span,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item > button.fi-sidebar-item-button span {
  font-size: inherit !important;
  font-weight: inherit !important;
}
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item .fi-sidebar-item > a.fi-sidebar-item-button,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item .fi-sidebar-item > button.fi-sidebar-item-button {
  font-size: 16px !important;
  font-weight: 400 !important;
}
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item .fi-sidebar-item > a.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item .fi-sidebar-item > button.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item .fi-sidebar-item > a.fi-sidebar-item-button span,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item .fi-sidebar-item > button.fi-sidebar-item-button span {
  font-size: inherit !important;
  font-weight: inherit !important;
}
/* Final class-based typography override (applied to all Filament panels) */
[class*="fi-panel-"] .fi-sidebar-group-button {
  font-size: 18px !important;
  font-weight: 700 !important;
}
[class*="fi-panel-"] .fi-sidebar-group-button .fi-sidebar-group-label,
[class*="fi-panel-"] .fi-sidebar-group-button span {
  font-size: inherit !important;
  font-weight: inherit !important;
}
[class*="fi-panel-"] .fi-sidebar-group-button .fi-sidebar-group-icon,
[class*="fi-panel-"] .fi-sidebar-group-button svg {
  width: 24px !important;
  height: 24px !important;
}
[class*="fi-panel-"] .fi-sidebar-item .fi-sidebar-item-button {
  font-size: 16px !important;
  font-weight: 700 !important;
}
[class*="fi-panel-"] .fi-sidebar-item .fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar-item .fi-sidebar-item-button span {
  font-size: inherit !important;
  font-weight: inherit !important;
}
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-group-items .fi-sidebar-item .fi-sidebar-item-button,
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav ul ul .fi-sidebar-item .fi-sidebar-item-button {
  font-size: 16px !important;
  font-weight: 400 !important;
}
[class*="fi-panel-"] .fi-sidebar-item .fi-sidebar-item-button .fi-sidebar-item-icon,
[class*="fi-panel-"] .fi-sidebar-item .fi-sidebar-item-button svg {
  width: 24px !important;
  height: 24px !important;
}

/* Remove icon background pills and force line-icon look */
[class*="fi-panel-"] .fi-sidebar-item-icon,
[class*="fi-panel-"] .fi-sidebar-group-icon {
  background: transparent !important;
  background-color: transparent !important;
  box-shadow: none !important;
  border-radius: 0 !important;
  padding: 0 !important;
}
[class*="fi-panel-"] .fi-sidebar-item-icon svg,
[class*="fi-panel-"] .fi-sidebar-group-icon svg,
[class*="fi-panel-"] .fi-sidebar-item-icon,
[class*="fi-panel-"] .fi-sidebar-group-icon {
  stroke: #2F4F2F !important;
  fill: none !important;
  stroke-width: 1.75 !important;
}

[class*="fi-panel-"] .fi-sidebar-header .fi-logo {
  height: 55px !important;
  max-height: 55px !important;
  width: auto !important;
}
/* Hide user avatar menu in the topbar (replace with MAP logo on right) */
[class*="fi-panel-"] .fi-topbar .fi-user-menu,
[class*="fi-panel-"] .fi-topbar .fi-user-menu-button,
[class*="fi-panel-"] .fi-topbar [data-user-menu],
[class*="fi-panel-"] .fi-topbar [data-user-menu-button] {
  display: none !important;
}

/* ═══════════════════════════════════════════════════════════════
   BUTTONS - Gradient CTA (Get Started style)
   ═══════════════════════════════════════════════════════════════ */
.fi-btn-primary {
  background: radial-gradient(143.2% 143.2% at 30% 30%, #F6C32E 0%, #F0B90B 50%, #D99E00 100%) !important;
  color: #2F4F2F !important; /* Military green text */
  border-radius: 16px !important;
  min-width: 200px !important;
  padding-block: 16px !important;
  padding-inline: 48px !important;
  font-size: 18px !important;
  font-weight: 600 !important;
  box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1) !important;
  border: none !important;
}
.fi-btn-primary:hover,
.fi-btn-primary:focus-visible {
  background: radial-gradient(143.2% 143.2% at 30% 30%, #f0b90b 0%, #d99e00 50%, #c08d00 100%) !important;
}
.fi-btn-secondary {
  background-color: transparent !important;
  border-color: #2F4F2F !important;
  color: #2F4F2F !important;
  border-width: 1px !important;
  border-radius: 16px !important;
  min-height: 48px !important;
}
.fi-btn-secondary:hover {
  background-color: rgba(47, 79, 47, 0.08) !important;
}

/* Utility class for non-Filament gradient buttons */
.btn-gradient-primary {
  background: radial-gradient(143.2% 143.2% at 30% 30%, #F6C32E 0%, #F0B90B 50%, #D99E00 100%) !important;
  color: #2F4F2F !important;
  border-radius: 16px !important;
  padding-block: 12px !important;
  padding-inline: 20px !important;
  font-size: 14px !important;
  font-weight: 600 !important;
  box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1) !important;
  border: none !important;
}
.btn-gradient-primary:hover,
.btn-gradient-primary:focus-visible {
  background: radial-gradient(143.2% 143.2% at 30% 30%, #f0b90b 0%, #d99e00 50%, #c08d00 100%) !important;
}

/* Match greeting card background to sidebar gradient */
.fi-wi-greeting {
  background: radial-gradient(143.2% 143.2% at 30% 30%, #F6C32E 0%, #F0B90B 50%, #D99E00 100%) !important;
}

/* Greeting hand SVG: explicit stroke (some browsers/themes do not apply currentColor to stroke) */
.fi-wi-greeting svg.fi-wi-greeting-hand,
.fi-wi-greeting svg.fi-wi-greeting-hand path {
  stroke: #2f4f2f !important;
  color: #2f4f2f !important;
}
.fi-wi-greeting svg.fi-wi-greeting-hand {
  fill: none !important;
}


/* Admin dashboard: all text Military Green (page title, greeting, KPIs, section headings) */
.fi-header-heading,
.fi-wi-greeting,
.fi-wi-greeting h1,
.fi-wi-greeting p,
.fi-wi-greeting a,
.fi-wi-stats-overview-header-heading,
.fi-wi-stats-overview-header-description,
.fi-wi-stats-overview-stat-label,
.fi-wi-stats-overview-stat-value,
.fi-wi-stats-overview-stat-description,
.fi-wi-stats-overview-stat-icon,
.fi-section-header-heading,
.fi-section-header-description {
  color: #2F4F2F !important;
}
.fi-wi-stats-overview-stat-icon {
  width: 30px !important;
  height: 30px !important;
  fill: transparent !important;
}
.fi-wi-stats-overview-header-description,
.fi-wi-stats-overview-stat-description {
  color: rgba(47, 79, 47, 0.85) !important;
}

/* Force sidebar item text/icons to Military Green in all states */
[class*="fi-panel-"] .fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar-item-button .fi-sidebar-item-icon,
[class*="fi-panel-"] .fi-sidebar-item-button:hover .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar-item-button:hover .fi-sidebar-item-icon,
[class*="fi-panel-"] .fi-sidebar-item.fi-active .fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar-item.fi-active .fi-sidebar-item-button .fi-sidebar-item-icon {
  color: #2F4F2F !important;
  fill: #2F4F2F !important;
}

/* Override Filament Tailwind so ALL sidebar text/icons stay Military Green */
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item-icon,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-group-label,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-group-icon,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-group-button .fi-sidebar-group-icon,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-group-items .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-group-items .fi-sidebar-item-icon,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-sub-group-items .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-sub-group-items .fi-sidebar-item-icon {
  color: #2F4F2F !important;
  fill: transparent !important;
}

/* Catch-all for Filament primary buttons */
.fi-btn.fi-btn-color-primary,
.fi-btn-primary {
  background: radial-gradient(143.2% 143.2% at 30% 30%, #F6C32E 0%, #F0B90B 50%, #D99E00 100%) !important;
  color: #2F4F2F !important;
  border-radius: 16px !important;
}
[class*="fi-panel-"] .fi-btn-primary .fi-btn-icon,
[class*="fi-panel-"] .fi-btn-primary .fi-btn-icon svg,
[class*="fi-panel-"] .btn-gradient-primary .fi-btn-icon,
[class*="fi-panel-"] .btn-gradient-primary svg {
  color: #2f4f2f !important;
}
[class*="fi-panel-"] .fi-btn-primary svg path,
[class*="fi-panel-"] .btn-gradient-primary svg path {
  stroke: #2f4f2f !important;
  fill: transparent !important;
}
[class*="fi-panel-"] .fi-btn-primary svg path[fill="none"],
[class*="fi-panel-"] .btn-gradient-primary svg path[fill="none"] {
  fill: none !important;
}
[class*="fi-panel-"] .fi-btn-primary svg path:not([fill="none"]),
[class*="fi-panel-"] .btn-gradient-primary svg path:not([fill="none"]) {
  fill: transparent !important;
}

/* Project-wide request: treat Tailwind gap-6 as 16px */
.gap-6 {
  gap: 1rem !important;
}

/* Enforce request: any literal 1.5rem gap should become 16px */
[style*="gap: 1.5rem"] {
  gap: 16px !important;
}
[style*="gap:1.5rem"] {
  gap: 16px !important;
}
[style*="column-gap: 1.5rem"],
[style*="column-gap:1.5rem"] {
  column-gap: 16px !important;
}
[style*="row-gap: 1.5rem"],
[style*="row-gap:1.5rem"] {
  row-gap: 16px !important;
}



/* Inline SVG: replace literal white fills/strokes inside Filament panels */
[class*="fi-panel-"] svg[fill="white"],
[class*="fi-panel-"] svg[fill="#fff"],
[class*="fi-panel-"] svg[fill="#ffffff"],
[class*="fi-panel-"] svg[fill="#FFFFFF"] {
  fill: #2f4f2f !important;
}
[class*="fi-panel-"] svg[stroke="white"],
[class*="fi-panel-"] svg[stroke="#fff"],
[class*="fi-panel-"] svg[stroke="#ffffff"],
[class*="fi-panel-"] svg[stroke="#FFFFFF"] {
  stroke: #2f4f2f !important;
}

/*
 * Primary buttons: Filament sets .fi-btn-icon.text-white (inherits on colored bg).
 * Force label + icons to military green for gradient + custom primary styles.
 */
[class*="fi-panel-"] .fi-btn.fi-color-primary:not(.fi-btn-outlined),
[class*="fi-panel-"] .fi-btn.fi-color-primary:not(.fi-btn-outlined) .fi-btn-label {
  color: #2f4f2f !important;
}
[class*="fi-panel-"] .fi-btn.fi-color-primary:not(.fi-btn-outlined) .fi-btn-icon,
[class*="fi-panel-"] .fi-btn.fi-color-primary:not(.fi-btn-outlined) .fi-btn-icon.text-white,
[class*="fi-panel-"] .fi-ac-action.fi-btn.fi-color-primary:not(.fi-btn-outlined) .fi-btn-icon {
  color: #2f4f2f !important;
}
[class*="fi-panel-"] .fi-btn.fi-color-primary:not(.fi-btn-outlined) svg,
[class*="fi-panel-"] .fi-btn.fi-color-primary:not(.fi-btn-outlined) .fi-btn-icon svg {
  color: #2f4f2f !important;
}
[class*="fi-panel-"] .fi-btn.fi-color-primary:not(.fi-btn-outlined) svg path {
  stroke: #2f4f2f !important;
  fill: transparent !important;
}
[class*="fi-panel-"] .fi-btn.fi-color-primary:not(.fi-btn-outlined) svg path[fill="none"] {
  fill: none !important;
}
[class*="fi-panel-"] .fi-btn.fi-color-primary:not(.fi-btn-outlined) svg path:not([fill="none"]) {
  fill: transparent !important;
}

/* Final hard override: keep all top-level menu labels bold across panels. */
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav > ul > li.fi-sidebar-item > a.fi-sidebar-item-button,
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav > ul > li.fi-sidebar-item > button.fi-sidebar-item-button,
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav > ul > li.fi-sidebar-group > button.fi-sidebar-group-button {
  font-weight: 700 !important;
}
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav > ul > li.fi-sidebar-item > a.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav > ul > li.fi-sidebar-item > button.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav > ul > li.fi-sidebar-item > a.fi-sidebar-item-button span,
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav > ul > li.fi-sidebar-item > button.fi-sidebar-item-button span,
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav > ul > li.fi-sidebar-group > button.fi-sidebar-group-button .fi-sidebar-group-label,
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav > ul > li.fi-sidebar-group > button.fi-sidebar-group-button span {
  font-weight: 700 !important;
}
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav ul ul .fi-sidebar-item .fi-sidebar-item-button,
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav ul ul .fi-sidebar-item .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav ul ul .fi-sidebar-item .fi-sidebar-item-button span {
  font-weight: 700 !important;
}
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav > ul > li.fi-sidebar-item.fi-active > a.fi-sidebar-item-button,
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav > ul > li.fi-sidebar-item.fi-active > button.fi-sidebar-item-button,
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav > ul > li.fi-sidebar-item.fi-active > a.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav > ul > li.fi-sidebar-item.fi-active > button.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav > ul > li.fi-sidebar-item.fi-active > a.fi-sidebar-item-button span,
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav > ul > li.fi-sidebar-item.fi-active > button.fi-sidebar-item-button span,
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav > ul > li.fi-sidebar-item > a.fi-sidebar-item-button[aria-current="page"],
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav > ul > li.fi-sidebar-item > a.fi-sidebar-item-button[aria-current="page"] .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav > ul > li.fi-sidebar-item > a.fi-sidebar-item-button[aria-current="page"] span {
  font-weight: 700 !important;
}
[class*="fi-panel-admin"] .fi-sidebar a.fi-sidebar-item-button[href$="/admin"],
[class*="fi-panel-admin"] .fi-sidebar a.fi-sidebar-item-button[href$="/admin/"],
[class*="fi-panel-campus-manager"] .fi-sidebar a.fi-sidebar-item-button[href$="/campus-manager"],
[class*="fi-panel-campus-manager"] .fi-sidebar a.fi-sidebar-item-button[href$="/campus-manager/"],
[class*="fi-panel-rector"] .fi-sidebar a.fi-sidebar-item-button[href$="/rector"],
[class*="fi-panel-rector"] .fi-sidebar a.fi-sidebar-item-button[href$="/rector/"],
[class*="fi-panel-college-mgmt"] .fi-sidebar a.fi-sidebar-item-button[href$="/college-mgmt"],
[class*="fi-panel-college-mgmt"] .fi-sidebar a.fi-sidebar-item-button[href$="/college-mgmt/"],
[class*="fi-panel-admin"] .fi-sidebar a.fi-sidebar-item-button[href$="/admin"] .fi-sidebar-item-label,
[class*="fi-panel-admin"] .fi-sidebar a.fi-sidebar-item-button[href$="/admin/"] .fi-sidebar-item-label,
[class*="fi-panel-campus-manager"] .fi-sidebar a.fi-sidebar-item-button[href$="/campus-manager"] .fi-sidebar-item-label,
[class*="fi-panel-campus-manager"] .fi-sidebar a.fi-sidebar-item-button[href$="/campus-manager/"] .fi-sidebar-item-label,
[class*="fi-panel-rector"] .fi-sidebar a.fi-sidebar-item-button[href$="/rector"] .fi-sidebar-item-label,
[class*="fi-panel-rector"] .fi-sidebar a.fi-sidebar-item-button[href$="/rector/"] .fi-sidebar-item-label,
[class*="fi-panel-college-mgmt"] .fi-sidebar a.fi-sidebar-item-button[href$="/college-mgmt"] .fi-sidebar-item-label,
[class*="fi-panel-college-mgmt"] .fi-sidebar a.fi-sidebar-item-button[href$="/college-mgmt/"] .fi-sidebar-item-label {
  font-weight: 700 !important;
}
/* Absolute final nav typography lock: all sidebar nav items must be bold. */
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item-button,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-group-button,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-group-label,
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav .fi-sidebar-item-button span,
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav .fi-sidebar-group-button span {
  font-weight: 700 !important;
}

/* Table toolbar: search prefix + filter + column toggle (icon buttons use gray-400) */
[class*="fi-panel-"] .fi-ta-header-toolbar .fi-icon-btn,
[class*="fi-panel-"] .fi-ta-header-toolbar .fi-icon-btn .fi-icon-btn-icon,
[class*="fi-panel-"] .fi-ta-header-toolbar .fi-icon-btn.text-gray-400 {
  color: #2f4f2f !important;
}
[class*="fi-panel-"] .fi-ta-header-toolbar .fi-icon-btn svg,
[class*="fi-panel-"] .fi-ta-header-toolbar .fi-icon-btn .fi-icon-btn-icon svg {
  color: #2f4f2f !important;
}
[class*="fi-panel-"] .fi-ta-header-toolbar .fi-icon-btn svg path {
  stroke: #2f4f2f !important;
}
[class*="fi-panel-"] .fi-ta-header-toolbar .fi-icon-btn svg path[fill="none"] {
  fill: none !important;
}
[class*="fi-panel-"] .fi-ta-header-toolbar .fi-icon-btn svg path:not([fill="none"]) {
  fill: #2f4f2f !important;
}

[class*="fi-panel-"] .fi-ta-search-field .fi-input-wrp-icon,
[class*="fi-panel-"] .fi-ta-search-field .fi-input-wrp-icon svg {
  color: #2f4f2f !important;
}
[class*="fi-panel-"] .fi-ta-search-field .fi-input-wrp-icon svg path {
  stroke: #2f4f2f !important;
  fill: #2f4f2f !important;
}
[class*="fi-panel-"] .fi-ta-search-field .fi-input-wrp-icon svg path[fill="none"] {
  fill: none !important;
}

/* Filter dropdown / modal trigger (any nested button + svg) */
[class*="fi-panel-"] .fi-ta-filters-dropdown button,
[class*="fi-panel-"] .fi-ta-filters-modal .fi-modal-trigger button {
  color: #2f4f2f !important;
}
[class*="fi-panel-"] .fi-ta-columns-dropdown button,
[class*="fi-panel-"] .fi-ta-columns-dropdown .fi-icon-btn,
[class*="fi-panel-"] .fi-ta-columns-toggle .fi-icon-btn,
[class*="fi-panel-"] .fi-ta-header-toolbar [class*="columns"] button {
  color: #2f4f2f !important;
}
[class*="fi-panel-"] .fi-ta-filters-dropdown button svg,
[class*="fi-panel-"] .fi-ta-filters-dropdown .fi-btn svg,
[class*="fi-panel-"] .fi-ta-filters-modal button.fi-btn svg,
[class*="fi-panel-"] .fi-ta-columns-dropdown button svg,
[class*="fi-panel-"] .fi-ta-columns-dropdown .fi-btn svg,
[class*="fi-panel-"] .fi-ta-columns-toggle button svg,
[class*="fi-panel-"] .fi-ta-header-toolbar [class*="columns"] button svg,
[class*="fi-panel-"] .fi-ta-header-toolbar [class*="fi-ta-filter"] svg,
[class*="fi-panel-"] .fi-ta-header-toolbar .fi-dropdown-panel-trigger button svg {
  color: #2f4f2f !important;
  stroke: #2f4f2f !important;
}
[class*="fi-panel-"] .fi-ta-filters-dropdown svg path,
[class*="fi-panel-"] .fi-ta-filters-modal svg path,
[class*="fi-panel-"] .fi-ta-columns-dropdown svg path,
[class*="fi-panel-"] .fi-ta-columns-toggle svg path,
[class*="fi-panel-"] .fi-ta-header-toolbar [class*="columns"] svg path {
  stroke: #2f4f2f !important;
}
[class*="fi-panel-"] .fi-ta-filters-dropdown svg path:not([fill="none"]),
[class*="fi-panel-"] .fi-ta-filters-modal svg path:not([fill="none"]),
[class*="fi-panel-"] .fi-ta-columns-dropdown svg path:not([fill="none"]),
[class*="fi-panel-"] .fi-ta-columns-toggle svg path:not([fill="none"]),
[class*="fi-panel-"] .fi-ta-header-toolbar [class*="columns"] svg path:not([fill="none"]) {
  fill: #2f4f2f !important;
}

/* Report center: form fields in one responsive row (Filament grid inside generate wrap) */
.report-center-page .report-center-form > div[class*="grid"],
.report-center-page .report-center-form form > div[class*="grid"] {
  align-items: flex-end !important;
}
.report-center-page .report-center-generate-wrap .fi-input-wrp,
.report-center-page .report-center-generate-wrap .fi-select-input {
  width: 100%;
}
.report-center-page .report-center-generate-wrap {
  border-radius: 0.75rem !important;
  border-color: #e5e7eb !important;
  padding: 1rem !important;
}
.report-center-page .report-center-form {
  display: grid !important;
  gap: 0.75rem !important;
}
@media (min-width: 1280px) {
  .report-center-page .report-center-form,
  .report-center-page .report-center-form.fi-form {
    grid-template-columns: minmax(0, 1fr) auto !important;
    align-items: end !important;
    column-gap: 1rem !important;
  }
  .report-center-page .report-center-form > div[class*="grid"],
  .report-center-page .report-center-form form > div[class*="grid"],
  .report-center-page .report-center-form .fi-fo,
  .report-center-page .report-center-form .fi-fo-component-ctn {
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 0.75rem !important;
    align-items: end !important;
  }
  .report-center-page .report-center-form > .mt-4 {
    margin-top: 0 !important;
  }
}
.report-center-page .report-center-form .fi-input-wrp,
.report-center-page .report-center-form .fi-select-input,
.report-center-page .report-center-form .fi-input,
.report-center-page .report-center-form .fi-select-input select {
  min-height: 2.75rem !important;
}
.report-center-page .report-center-form .fi-fo-field-wrp {
  margin-bottom: 0 !important;
}
.report-center-page .report-center-form .btn-gradient-primary {
  min-height: 2.75rem !important;
  min-width: 170px !important;
  border-radius: 0.75rem !important;
  padding-inline: 1rem !important;
}

/* Campus Manager report center: mimic single-row filters + action layout */
.campus-report-center-page .campus-report-center-form {
  display: grid !important;
  gap: 1rem !important;
}
@media (min-width: 1280px) {
  .campus-report-center-page .campus-report-center-form {
    grid-template-columns: minmax(0, 1fr) auto !important;
    align-items: end !important;
    column-gap: 1rem !important;
  }
}
.campus-report-center-page .campus-report-center-form > div[class*="grid"] {
  align-items: flex-end !important;
}
.campus-report-center-page .campus-report-center-form .fi-input-wrp,
.campus-report-center-page .campus-report-center-form .fi-select-input {
  width: 100%;
}
.campus-report-center-page > section + section {
  margin-top: 26px !important;
}

/* Reports sections: force 26px top spacing on both blocks */
.report-center-page section[aria-labelledby="reports-generate-heading"],
.report-center-page section[aria-labelledby="reports-available-heading"],
.report-index-page section[aria-labelledby="reports-generate-heading"],
.report-index-page section[aria-labelledby="reports-available-heading"] {
  margin-top: 26px !important;
}
@media (min-width: 1280px) {
  .report-center-page .report-center-form {
    grid-template-columns: minmax(0, 1fr) auto !important;
    column-gap: 1rem !important;
    align-items: end !important;
  }
}

/* Available Reports: icons fixed 30×30, brand color #2F4F2F (Report Center + Report Index) */
.report-center-page section[aria-labelledby="reports-available-heading"] .report-card-icon,
.report-index-page section[aria-labelledby="reports-available-heading"] .report-card-icon,
.campus-report-center-page .report-card-icon,
/* Rector reports page uses custom wrapper (no aria-labelledby dependency) */
[class*="fi-panel-rector"] .rector-available-reports .report-card-icon {
  width: 30px !important;
  height: 30px !important;
  min-width: 30px !important;
  min-height: 30px !important;
  color: #2f4f2f !important;
}
.report-center-page section[aria-labelledby="reports-available-heading"] .report-card-icon svg,
.report-index-page section[aria-labelledby="reports-available-heading"] .report-card-icon svg,
.campus-report-center-page .report-card-icon svg,
[class*="fi-panel-rector"] .rector-available-reports .report-card-icon svg {
  width: 30px !important;
  height: 30px !important;
  color: #2f4f2f !important;
}
.report-center-page section[aria-labelledby="reports-available-heading"] .report-card-icon svg path,
.report-index-page section[aria-labelledby="reports-available-heading"] .report-card-icon svg path,
.campus-report-center-page .report-card-icon svg path,
[class*="fi-panel-rector"] .rector-available-reports .report-card-icon svg path,
[class*="fi-panel-rector"] .rector-available-reports .report-card-icon svg circle,
[class*="fi-panel-rector"] .rector-available-reports .report-card-icon svg rect,
[class*="fi-panel-rector"] .rector-available-reports .report-card-icon svg polygon,
[class*="fi-panel-rector"] .rector-available-reports .report-card-icon svg line {
  stroke: #2f4f2f !important;
  fill: #2f4f2f !important;
}
.report-center-page section[aria-labelledby="reports-available-heading"] .report-card-icon svg path[fill="none"],
.report-index-page section[aria-labelledby="reports-available-heading"] .report-card-icon svg path[fill="none"],
.campus-report-center-page .report-card-icon svg path[fill="none"],
[class*="fi-panel-rector"] .rector-available-reports .report-card-icon svg path[fill="none"],
[class*="fi-panel-rector"] .rector-available-reports .report-card-icon svg circle[fill="none"],
[class*="fi-panel-rector"] .rector-available-reports .report-card-icon svg rect[fill="none"],
[class*="fi-panel-rector"] .rector-available-reports .report-card-icon svg polygon[fill="none"] {
  fill: none !important;
}

/* Rector reports: Available Reports grid should be 2-column like reference image */
[class*="fi-panel-rector"] .rector-available-reports .rector-available-reports-grid {
  display: grid !important;
  grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
}
@media (min-width: 768px) {
  [class*="fi-panel-rector"] .rector-available-reports .rector-available-reports-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
  }
}

/* Admin panels sidebar: requested menu hover and submenu styling */
[class*="fi-panel-"] li.fi-sidebar-item:hover > a.fi-sidebar-item-button,
[class*="fi-panel-"] li.fi-sidebar-item:hover > button.fi-sidebar-item-button,
[class*="fi-panel-"] li.fi-sidebar-item > a.fi-sidebar-item-button:hover,
[class*="fi-panel-"] li.fi-sidebar-item > button.fi-sidebar-item-button:hover {
  background: radial-gradient(143.2% 143.2% at 30% 30%, #F6C32E 0%, #F0B90B 50%, #D99E00 100%) !important;
  border-radius: 5px !important;
}
[class*="fi-panel-"] li.fi-sidebar-item:hover > a.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] li.fi-sidebar-item:hover > button.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] li.fi-sidebar-item:hover > a.fi-sidebar-item-button .fi-sidebar-item-icon,
[class*="fi-panel-"] li.fi-sidebar-item:hover > button.fi-sidebar-item-button .fi-sidebar-item-icon {
  color: #2F4F2F !important;
}
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-group-items,
[class*="fi-panel-"] .fi-sidebar nav.fi-sidebar-nav ul ul {
  background-color: #f8fafc !important;
  border-radius: 5px !important;
}
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-group-items {
  gap: 8px !important;
}
[class*="fi-panel-"] li.fi-sidebar-group.fi-active > .fi-sidebar-group-button,
[class*="fi-panel-"] .fi-sidebar-group-button:hover,
[class*="fi-panel-"] .fi-sidebar-group-button:focus-visible {
  background: radial-gradient(143.2% 143.2% at 30% 30%, #F6C32E 0%, #F0B90B 50%, #D99E00 100%) !important;
  border-radius: 5px !important;
}

@media (min-width: 1024px) {
  /* Collapsed footer profile: hide text, keep centered avatar only */
  [class*="fi-panel-"] .fi-sidebar:not(.fi-sidebar-open) .account-sticky-details {
    display: none !important;
  }
  [class*="fi-panel-"] .fi-sidebar:not(.fi-sidebar-open) .account-sticky-link {
    justify-content: center !important;
    gap: 0 !important;
  }
  [class*="fi-panel-"] .fi-sidebar:not(.fi-sidebar-open) .account-sticky-avatar {
    width: 2rem !important;
    height: 2rem !important;
  }
}

/* Collapsed sidebar: keep active/hover highlight consistent */
@media (min-width: 1024px) {
  [class*="fi-panel-"] .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item.fi-active > a.fi-sidebar-item-button,
  [class*="fi-panel-"] .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item.fi-active > button.fi-sidebar-item-button,
  [class*="fi-panel-"] .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item > a.fi-sidebar-item-button[aria-current="page"],
  [class*="fi-panel-"] .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item > button.fi-sidebar-item-button[aria-current="page"] {
    background: radial-gradient(143.2% 143.2% at 30% 30%, #F6C32E 0%, #F0B90B 50%, #D99E00 100%) !important;
    border-radius: 5px !important;
  }
  [class*="fi-panel-"] .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item.fi-active > a.fi-sidebar-item-button .fi-sidebar-item-label,
  [class*="fi-panel-"] .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item.fi-active > button.fi-sidebar-item-button .fi-sidebar-item-label,
  [class*="fi-panel-"] .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item.fi-active > a.fi-sidebar-item-button .fi-sidebar-item-icon,
  [class*="fi-panel-"] .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item.fi-active > button.fi-sidebar-item-button .fi-sidebar-item-icon,
  [class*="fi-panel-"] .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item > a.fi-sidebar-item-button[aria-current="page"] .fi-sidebar-item-label,
  [class*="fi-panel-"] .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item > button.fi-sidebar-item-button[aria-current="page"] .fi-sidebar-item-label {
    color: #2F4F2F !important;
  }
}

/* Campus Manager dashboard filters: active Hostel/Time Range button style */
[class*="fi-panel-campus-manager"] .campus-dashboard-filter-active {
  background: radial-gradient(143.2% 143.2% at 30% 30%, #F6C32E 0%, #F0B90B 50%, #D99E00 100%) !important;
  color: #2a472a !important;
  border-color: transparent !important;
}

/* Attendance closure chart: align visual block with Request KPIs column */
.fi-wi-attendance-closure-trend .fi-section-content > div > div {
  min-height: 380px;
}
@media (max-width: 640px) {
  .fi-wi-attendance-closure-trend .fi-section-content > div > div {
    min-height: 240px;
  }
}

/* Super Admin dashboard: enforce equal visual heights for these two cards */
.fi-wi-students-by-tenant-widget .fi-section-content > div > div,
.fi-wi-students-by-tenant .fi-section-content > div > div,
.fi-wi-tickets-by-priority-chart .fi-section-content > div > div,
.fi-wi-tickets-by-priority .fi-section-content > div > div {
  min-height: 280px !important;
  max-height: 280px !important;
}
.fi-wi-students-by-tenant-widget canvas,
.fi-wi-students-by-tenant canvas,
.fi-wi-tickets-by-priority-chart canvas,
.fi-wi-tickets-by-priority canvas {
  max-height: 220px !important;
}

/* Rector table overlap guard: prevent left clipping in request tables. */
[class*="fi-panel-rector"] .fi-section-content,
[class*="fi-panel-rector"] .fi-ta-content,
[class*="fi-panel-rector"] .fi-ta-table {
  overflow: visible !important;
}
[class*="fi-panel-rector"] .fi-ta-table {
  margin-left: 0 !important;
  transform: none !important;
}
</style>
