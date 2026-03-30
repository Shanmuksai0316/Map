# DesignSystem_v1.0.md — MAP‑HMS

**Release:** v1.0  
**Date:** 26‑Sep‑2025 (IST)  
**Surfaces:** Web (Filament v3 + Tailwind), Mobile (React Native)  
**Theme:** Light‑mode only (v1). Accessibility AA+ on core text/icons.

> Goal: consistent, fast UIs that are easy to ship by a 2‑dev team. This system defines tokens, components, interaction patterns, and code snippets for both surfaces.

---

## 1) Design Tokens

### 1.1 Color palette (AA on body text)
- **Primary (Military Green)** `--c-primary`: #2F4F2F, hover #1F3F1F, focus ring #4F6F4F.  
  *Usage: Headings, primary text, icons, borders, dashboards (represents discipline, trust, operations, safety)*
- **Accent (Golden Yellow)** `--c-accent`: #D4AF37.  
  *Usage: Logo highlights, icons, key metrics, call-outs (represents excellence, value, leadership)*
- **Neutral** `--c-neutral-*`: 50 #FAFAFB, 100 #F3F4F6, 200 #E5E7EB, 300 #D1D5DB, 400 #9CA3AF, 500 #6B7280, 600 #4B5563, 700 #374151, 800 #1F2937, 900 #111827.  
- **Success**: #16A34A, **Warning**: #D4AF37 (Golden Yellow), **Error**: #DC2626, **Info**: #0EA5E9.  
- **Background (Pure White)**: #FFFFFF, **Surface**: #FFFFFF, **Muted surface**: #F8FAFC.  
  *Usage: Backgrounds for documents, dashboards, clean & professional look*
- **Chart series (7)**: `#2F4F2F, #D4AF37, #16A34A, #DC2626, #0EA5E9, #9333EA, #EF4444`.

### 1.2 Typography
- **Web:** `Inter` (system stack fallback); sizes —  
  - H1 28/36, H2 24/32, H3 20/28, Body 16/24, Small 14/20, Caption 12/16.  
- **Mobile:** `System` (SF/Roboto); sizes —  
  - Title 24/32, Subtitle 20/28, Body 16/24, Small 14/20, Caption 12/16.

### 1.3 Spacing & Radius
- **Spacing scale (4‑pt):** 0, 2, 4, 8, 12, 16, 20, 24, 32, 40.  
- **Radius:** xs 6, sm 10, md 14, lg 20, xl 24 (cards/buttons `lg`), pills full.  
- **Elevation:**  
  - Card: `shadow-[0_1px_2px_rgba(0,0,0,0.06),0_4px_12px_rgba(0,0,0,0.06)]`  
  - Modal/Sheet: `shadow-[0_8px_24px_rgba(0,0,0,0.14)]`

### 1.4 Iconography & Imagery
- **Icons:** Lucide (web) / Ionicons (mobile).  
- **Illustrations:** none in v1; use simple empty‑state glyphs only.

---

## 2) Tailwind (Web) — baseline config
```js
// tailwind.config.js (snippet)
module.exports = {
  content: ["./resources/**/*.blade.php","./resources/**/*.php","./resources/**/*.js"],
  theme: {
    extend: {
      colors: {
        primary: { DEFAULT: "#2F4F2F", 700: "#1F3F1F" },  // Military Green
        accent: "#D4AF37",  // Golden Yellow
        success: "#16A34A", warning: "#D4AF37", danger: "#DC2626", info: "#0EA5E9",
      },
      borderRadius: { lg: "20px", xl: "24px" },
      boxShadow: {
        card: "0 1px 2px rgba(0,0,0,0.06), 0 4px 12px rgba(0,0,0,0.06)",
        modal: "0 8px 24px rgba(0,0,0,0.14)",
      }
    }
  }
}
```

**Filament theming:** set `primary` color to `#2F4F2F` (Military Green); accent `#D4AF37` (Golden Yellow) for highlights; cards use `shadow-card` + `rounded-xl`; tables with dense rows.

---

## 3) RN Theme (Mobile) — baseline
```ts
// theme.ts
export const theme = {
  colors: {
    bg: "#FFFFFF",           // Pure White background
    text: "#2F4F2F",         // Military Green for text
    muted: "#6B7280",
    primary: "#2F4F2F",      // Military Green
    accent: "#D4AF37",       // Golden Yellow (for highlights, icons, metrics)
    success: "#16A34A",
    warning: "#D4AF37",      // Golden Yellow
    error: "#DC2626",
    surface: "#FFFFFF",
    surfaceMuted: "#F8FAFC",
  },
  spacing: [0,2,4,8,12,16,20,24,32,40],
  radius: { xs:6, sm:10, md:14, lg:20, xl:24 },
};
```

**Usage:** keep buttons ≥ 44px tap target; list rows 56–64px; chips 36–40px height.

---

## 4) Components — Web (Filament) patterns
- **Cards:** rounded‑xl, `shadow-card`, 16–24px padding; header with title + small subtitle.  
- **Tables:** sticky header, zebra rows, compact density, right‑aligned numerics; sort + filter top‑right.  
- **Forms:** 1–2 columns; clear labels; helper text under inputs; validation on blur; error text in `danger` color.  
- **Buttons:** Primary (solid), Secondary (outline), Tertiary (link). Destructive = `danger`.  
- **Modals/Sheets:** use for create/edit; keep under 600px width; focus trap & ESC close.  
- **Empty states:** icon + 1‑line description + primary action.  
- **Toasts:** top‑right; success/warning/error colors.  
- **Charts (Chart.js):** light grid, rounded corners, series from palette order; show exact values on hover; y‑axis starts at zero for counts.

---

## 5) Components — Mobile (RN) patterns
- **Top bar:** Title-centered; back chevron on left; actions (1–2) on right.  
- **Cards & Lists:** 12–16px padding; 8–12px between cards; list dividers 1px `#E5E7EB`.  
- **Buttons:** Primary solid; Secondary outline; Destructive red; min width 48% in footers for double CTAs.  
- **Chips:** Large tappable chips (Attendance: Present/Absent), 40px height; selected state solid fill, unselected outline.  
- **Search:** Top search bar with debounce; results show avatar (initials), name, room small.  
- **Offline banners:** yellow bar “Pending Sync: N events” on Guard/Warden screens.  
- **Error & Empty:** Big icon, 1‑line copy, retry button; skeleton loaders for lists.  
- **Photos:** square thumbnails 96px; tap to full‑screen; upload cap 3 per post.

---

## 6) Critical Screens — key rules
### 6.1 Guard (Gate)
- **Home:** Big “Scan” button + search; today’s approved list; Emergency Exit button in red outline; pending sync chip.  
- **Exit/Entry:** success = green check sheet; errors readable (not codes).  
- **Offline:** Greyed network icon; only cached Approved passes shown; Emergency Exit requires note.

### 6.2 Warden (Attendance)
- **Room grid:** 2–3 columns of cards, status subtotals; submitted rooms dimmed.  
- **Student list:** Large chips for Present/Absent; Leave rows grey + locked; comment field auto‑expands on Absent.

### 6.3 Rector (Approvals)
- **Inbox:** Density medium; checkboxes + bulk Approve/Decline at top; filters sticky.
- **Decision dialog:** reason optional; step‑up OTP; show impact on attendance if overnight.
- **SLA Status Badges:**
  - **Purpose:** Visual indicators for approval SLA status in Out-Pass (2h) and Leave (4h) tabs
  - **Colors & Thresholds:**
    - 🟢 **Green** (`success`): > 25% SLA remaining (>30min for Out-Pass, >1h for Leave)
    - 🟡 **Yellow** (`warning`): ≤ 25% SLA remaining (≤30min for Out-Pass, ≤1h for Leave)
    - 🔴 **Red** (`danger`): SLA breached or overdue
  - **Format:** "Xh Ym left" / "Overdue: +Xh Ym"
  - **Usage:** Display in table columns and filters ("SLA Breached", "Due Soon")

### 6.4 Student (Self‑service)
- **Home:** Quick actions grid; upcoming cards; notifications bell.  
- **Out‑Pass:** 3 radio buttons (Normal/Leave/Sick) + Overnight switch + Note; submit; show state machine chip (Pending/Approved/etc.).

---

## 7) Motion & Feedback
- **Durations:** Micro 120–160ms, Standard 200–240ms, Modal 280–320ms.  
- **Easings:** ease‑out for entrances, ease‑in for exits.  
- **RN:** Use `react-native-reanimated` for performance where needed; otherwise `Animated` API.  
- **Web:** Minimal transitions (Tailwind `transition‑colors`/`opacity`), no heavy parallax.

---

## 8) Accessibility & Copy
- **Contrast:** Body text ≥ 4.5:1 on white; buttons ≥ 3:1.  
- **Touch:** 44×44px minimum; hit slop 8px.  
- **Labels:** a11yLabel on RN; `aria‑` for web forms and tables.  
- **Copy style:** short verbs (“Approve”, “Mark Ready”, “Submit Room”); avoid jargon; show friendly descriptions for errors; never surface raw error codes to users.

---

## 9) Chart.js Defaults (Web)
```js
// chart-defaults.js
import { Chart, ArcElement, LineElement, BarElement, CategoryScale, LinearScale, Tooltip, Legend } from 'chart.js'
Chart.register(ArcElement, LineElement, BarElement, CategoryScale, LinearScale, Tooltip, Legend)
Chart.defaults.font.family = 'Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial'
Chart.defaults.color = '#111827'
Chart.defaults.elements.line.tension = 0.3
Chart.defaults.plugins.legend.position = 'bottom'
Chart.defaults.plugins.tooltip.mode = 'index'
```

---

## 10) Component Inventory (v1)
**Web:** Button, Input, Select, Date/Time picker, Search field, Tag/Badge, Card, Table, Pagination, Modal/Sheet, Tabs, Toast, EmptyState, ChartCard.  
**Mobile:** Button, Chip, TextInput, Select/Picker, Search bar, Card, ListItem, Badge, Toast/Snackbar, Modal/BottomSheet, PhotoPicker, EmptyState, Progress.

---

## 11) Assets & Export
- **Icons:** Lucide SVGs (web) via tree‑shaken imports; Ionicons RN via `react-native-vector-icons`.  
- **Logos:** College logos optional in future; for now only Tenant name in headers.  
- **Favicon/App icon:** Provided by MAP brand pack (to be added to repo `apps/mobile/assets/` and `public/`).

---

## 12) Quality Checklist (UI)
- [ ] Text sizes follow scale; no 13px.  
- [ ] Buttons ≥ 44px/high; chip ≥ 36px/high.  
- [ ] Primary color used sparingly; neutral for tables.  
- [ ] Loading: skeletons for lists; spinners ≤ 1s only.  
- [ ] Errors: inline + toast; never cryptic codes.  
- [ ] Empty states: icon + action.  
- [ ] Dark backgrounds **not** used (light‑only).  
- [ ] Screens tested at 320px width (mobile) and 1366px (web) minimums.

---

## 13) Future (backlog)
- Dark mode tokens; per‑tenant theming; richer charts (comparatives); icon audit for RTL/i18n.
