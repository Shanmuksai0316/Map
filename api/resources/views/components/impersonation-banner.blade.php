{{-- Admin panel: olive green side nav (injected at body start so it applies) --}}
<style>
[class*="fi-panel-"] aside.fi-sidebar,
[class*="fi-panel-"] .fi-sidebar,
[class*="fi-panel-"] nav.fi-sidebar-nav,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-content,
[class*="fi-panel-"] .fi-sidebar-content{background-color:#2a472a!important;background:#2a472a!important}
[class*="fi-panel-"] .fi-sidebar-group-label,
[class*="fi-panel-"] span.fi-sidebar-group-label,
[class*="fi-panel-"] .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar-item-icon{color:#fff!important;fill:#fff!important}
[class*="fi-panel-"] li.fi-sidebar-item>a.fi-sidebar-item-button{display:flex!important;width:100%!important}
[class*="fi-panel-"] li.fi-sidebar-item:hover>a.fi-sidebar-item-button,
[class*="fi-panel-"] li.fi-sidebar-item>a.fi-sidebar-item-button:hover,
[class*="fi-panel-"] li.fi-sidebar-item.fi-active>a.fi-sidebar-item-button,
[class*="fi-panel-"] li.fi-sidebar-item.fi-active.fi-sidebar-item-active>a.fi-sidebar-item-button{background-color:#fff!important}
[class*="fi-panel-"] li.fi-sidebar-item:hover>a.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] li.fi-sidebar-item:hover>a.fi-sidebar-item-button .fi-sidebar-item-icon,
[class*="fi-panel-"] li.fi-sidebar-item>a.fi-sidebar-item-button:hover .fi-sidebar-item-label,
[class*="fi-panel-"] li.fi-sidebar-item>a.fi-sidebar-item-button:hover .fi-sidebar-item-icon,
[class*="fi-panel-"] li.fi-sidebar-item.fi-active>a.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] li.fi-sidebar-item.fi-active>a.fi-sidebar-item-button .fi-sidebar-item-icon,
[class*="fi-panel-"] li.fi-sidebar-item.fi-active.fi-sidebar-item-active>a.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] li.fi-sidebar-item.fi-active.fi-sidebar-item-active>a.fi-sidebar-item-button .fi-sidebar-item-icon{color:#2a472a!important;fill:#2a472a!important}
[class*="fi-panel-"] li.fi-sidebar-item:hover .bg-primary-600,[class*="fi-panel-"] li.fi-sidebar-item.fi-active .bg-primary-600{background-color:#2f4f2f!important}
[class*="fi-panel-"] li.fi-sidebar-item>a.fi-sidebar-item-button:focus-visible{outline:none!important;box-shadow:none!important}
/* Typography: main items */
[class*="fi-panel-"] nav.fi-sidebar-nav>ul>li.fi-sidebar-item>a.fi-sidebar-item-button,
[class*="fi-panel-"] nav.fi-sidebar-nav>ul>li.fi-sidebar-item>button.fi-sidebar-item-button{font-size:18px!important;font-weight:700!important;line-height:1.3!important}
[class*="fi-panel-"] nav.fi-sidebar-nav>ul>li.fi-sidebar-item>a.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] nav.fi-sidebar-nav>ul>li.fi-sidebar-item>button.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] nav.fi-sidebar-nav>ul>li.fi-sidebar-item>a.fi-sidebar-item-button span,
[class*="fi-panel-"] nav.fi-sidebar-nav>ul>li.fi-sidebar-item>button.fi-sidebar-item-button span{font-size:inherit!important;font-weight:inherit!important}
/* Typography: sub-items */
[class*="fi-panel-"] nav.fi-sidebar-nav ul ul li.fi-sidebar-item>a.fi-sidebar-item-button,
[class*="fi-panel-"] nav.fi-sidebar-nav ul ul li.fi-sidebar-item>button.fi-sidebar-item-button{font-size:16px!important;font-weight:700!important;line-height:1.3!important}
[class*="fi-panel-"] nav.fi-sidebar-nav ul ul li.fi-sidebar-item>a.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] nav.fi-sidebar-nav ul ul li.fi-sidebar-item>button.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] nav.fi-sidebar-nav ul ul li.fi-sidebar-item>a.fi-sidebar-item-button span,
[class*="fi-panel-"] nav.fi-sidebar-nav ul ul li.fi-sidebar-item>button.fi-sidebar-item-button span{font-size:inherit!important;font-weight:inherit!important}
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item>a.fi-sidebar-item-button,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item>button.fi-sidebar-item-button{font-size:18px!important;font-weight:700!important}
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item>a.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item>button.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item>a.fi-sidebar-item-button span,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item>button.fi-sidebar-item-button span{font-size:inherit!important;font-weight:inherit!important}
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item .fi-sidebar-item>a.fi-sidebar-item-button,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item .fi-sidebar-item>button.fi-sidebar-item-button{font-size:16px!important;font-weight:700!important}
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item .fi-sidebar-item>a.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item .fi-sidebar-item>button.fi-sidebar-item-button .fi-sidebar-item-label,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item .fi-sidebar-item>a.fi-sidebar-item-button span,
[class*="fi-panel-"] .fi-sidebar .fi-sidebar-item .fi-sidebar-item>button.fi-sidebar-item-button span{font-size:inherit!important;font-weight:inherit!important}
[class*="fi-panel-"] .fi-sidebar-group-button{font-size:18px!important;font-weight:700!important}
[class*="fi-panel-"] .fi-sidebar-group-button .fi-sidebar-group-label,[class*="fi-panel-"] .fi-sidebar-group-button span{font-size:inherit!important;font-weight:inherit!important}
[class*="fi-panel-"] .fi-sidebar-group-button .fi-sidebar-group-icon,[class*="fi-panel-"] .fi-sidebar-group-button svg{width:18px!important;height:18px!important}
[class*="fi-panel-"] .fi-sidebar-item .fi-sidebar-item-button{font-size:16px!important;font-weight:700!important}
[class*="fi-panel-"] .fi-sidebar-item .fi-sidebar-item-button .fi-sidebar-item-label,[class*="fi-panel-"] .fi-sidebar-item .fi-sidebar-item-button span{font-size:inherit!important;font-weight:inherit!important}
[class*="fi-panel-"] .fi-sidebar-item .fi-sidebar-item-button .fi-sidebar-item-icon,[class*="fi-panel-"] .fi-sidebar-item .fi-sidebar-item-button svg{width:16px!important;height:16px!important}
[class*="fi-panel-"] .fi-sidebar-header .fi-logo{height:55px!important;max-height:55px!important;width:auto!important}
</style>
@if (session('impersonating_from'))
    <div class="bg-amber-400 text-black px-4 py-3 flex items-center justify-between shadow-md z-50">
        <div class="flex flex-col">
            <span class="font-semibold">IMPERSONATION MODE</span>
            <span class="text-sm">
                Viewing as {{ auth()->user()->name ?? 'Tenant User' }} (tenant: {{ session('impersonated_tenant_name') ?? 'active' }}).
                Stop impersonation to return to Super Admin.
            </span>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.stop-impersonation') }}"
               class="inline-flex items-center px-3 py-2 rounded-md bg-black text-white text-sm font-semibold hover:bg-gray-900">
                Stop Impersonation
            </a>
        </div>
    </div>
@endif
