const { test, expect } = require('playwright/test');
const fs = require('fs');
const path = require('path');

const repoRoot = path.resolve(__dirname, '..', '..');

function read(relPath) {
  return fs.readFileSync(path.join(repoRoot, relPath), 'utf8');
}

test.describe('Campus Manager UAT regression contracts', () => {
  test('CA-003 tenant selection flow is enforced in auth and navigation', async () => {
    const authStore = read('mobile/src/shared/store/auth.store.ts');
    const rootNavigator = read('mobile/src/shared/navigation/RootNavigator.tsx');

    expect(authStore).toMatch(/if\s*\(!selectedTenant\)\s*{\s*selectedTenant\s*=\s*await get\(\)\.autoDetectTenant\(phone\)/s);
    expect(rootNavigator).toMatch(/if\s*\(!isAuthenticated\s*\|\|\s*!selectedTenant\)\s*{/s);
  });

  test('CA-005 + CA-006 dashboard header and greeting logo wiring are present', async () => {
    const header = read('mobile/src/staff/components/StaffDashboardHeader.tsx');
    const dashboard = read('mobile/src/staff/screens/campus-manager/CampusManagerDashboard.tsx');
    const collegeLogo = read('mobile/src/shared/components/CollegeLogo.tsx');

    expect(header).toContain("karta-logo.png");
    expect(header).toMatch(/width:\s*62/);
    expect(dashboard).toMatch(/<CollegeLogo[\s\S]*fillContainer/);
    expect(dashboard).toMatch(/collegeName=\{selectedTenant\?\.name \|\| 'College'\}/);
    expect(collegeLogo).toMatch(/logoFill:\s*{[\s\S]*width:\s*'92%'/);
  });

  test('CA-009 dashboard tiles route to correct tab/stack names', async () => {
    const dashboard = read('mobile/src/staff/screens/campus-manager/CampusManagerDashboard.tsx');
    expect(dashboard).toContain("navigateTo('MyChecklist')");
    expect(dashboard).toContain("navigateTo('Requests')");
    expect(dashboard).toContain("navigateTo('Comm Box')");
    expect(dashboard).toContain("navigateTo('My Staff')");
    expect(dashboard).toContain("navigateTo('Profile')");
  });

  test('CA-011 + CA-012 notifications list/back path is wired', async () => {
    const tabs = read('mobile/src/staff/navigation/campus-manager.tabs.tsx');
    const dashboard = read('mobile/src/staff/screens/campus-manager/CampusManagerDashboard.tsx');
    const commBox = read('mobile/src/staff/screens/campus-manager/CommBoxScreen.tsx');

    expect(tabs).toContain('<Stack.Screen name="Notifications" component={CommBoxScreen} />');
    expect(dashboard).toContain("onNotificationsPress={() => navigateTo('Notifications')}");
    expect(commBox).toMatch(/onPress=\{\(\) => navigation\.goBack\(\)\}/);
  });

  test('CA-014 communication detail modal opens with content', async () => {
    const commBox = read('mobile/src/staff/screens/campus-manager/CommBoxScreen.tsx');
    expect(commBox).toMatch(/setSelectedNotice\(notification\)/);
    expect(commBox).toMatch(/setShowDetailModal\(true\)/);
    expect(commBox).toMatch(/<Modal[\s\S]*visible=\{showDetailModal\}/);
    expect(commBox).toMatch(/<Text style=\{styles\.modalBodyText\}>\{selectedNotice\?\.body\}<\/Text>/);
  });

  test('CA-019 profile screen route and header consistency exist', async () => {
    const tabs = read('mobile/src/staff/navigation/campus-manager.tabs.tsx');
    const profile = read('mobile/src/staff/screens/campus-manager/CampusManagerProfileScreen.tsx');
    expect(tabs).toContain('<Stack.Screen name="Profile" component={CampusManagerProfileScreen} />');
    expect(profile).toMatch(/const HEADER_CONTENT_HEIGHT = 67/);
  });

  test('CA-021 + CA-023 checklist tabs, completion endpoints, and double-tap guard are present', async () => {
    const checklist = read('mobile/src/staff/screens/campus-manager/CampusManagerChecklistScreen.tsx');

    expect(checklist).toContain("setActiveTab('my-checklist')");
    expect(checklist).toContain("setActiveTab('staff-checklist')");
    expect(checklist).toContain('/mobile/campus-manager/checklists/items/${task.index}/complete');
    expect(checklist).toContain('/mobile/campus-manager/checklists/items/${taskIndex}/photo');
    expect(checklist).toMatch(/if\s*\(loadingStaffDetailFor === staff\.user_id\)\s*{\s*return;\s*}/s);
    expect(checklist).toMatch(/disabled=\{loadingStaffDetailFor === item\.user_id\}/);
  });

  test('CA-029 + CA-030 requests search and detail popup are robust', async () => {
    const requests = read('mobile/src/staff/screens/campus-manager/RequestsHubScreen.tsx');

    expect(requests).toMatch(/const asText = \(value: unknown\): string => {/);
    expect(requests).toMatch(/asText\(req\.student_name \|\| req\.user_name\)\.toLowerCase\(\)\.includes\(query\)/);
    expect(requests).toMatch(/asText\(req\.room\)\.toLowerCase\(\)\.includes\(query\)/);
    expect(requests).toMatch(/if \(!dateString\) return 'N\/A'/);
    expect(requests).toMatch(/if \(Number\.isNaN\(date\.getTime\(\)\)\) return 'N\/A'/);
    expect(requests).toMatch(/<Modal[\s\S]*visible=\{showPopup\}/);
    expect(requests).toMatch(/renderPopupFields\(\)\?\.map/);
  });

  test('CA-032 emergency unacknowledged fallback exists for missing endpoint', async () => {
    const emergencyStore = read('mobile/src/shared/store/emergency.store.ts');
    expect(emergencyStore).toMatch(/if \(error\?\.response\?\.status === 404\) {/);
    expect(emergencyStore).toMatch(/await emergencyGet\('\/incidents',\s*{[\s\S]*acknowledged:\s*0/);
    expect(emergencyStore).toMatch(/set\(\{ unacknowledgedCount: fallbackCount \}\)/);
  });

  test('CA-035 my staff role badge and department badge are both shown', async () => {
    const myStaff = read('mobile/src/staff/screens/campus-manager/MyStaffScreen.tsx');
    expect(myStaff).toMatch(/<View style=\{styles\.badgesRow\}>/);
    expect(myStaff).toMatch(/<View style=\{\[styles\.roleBadge/);
    expect(myStaff).toMatch(/<Text style=\{\[styles\.roleText/);
    expect(myStaff).toMatch(/<View style=\{\[styles\.departmentBadge/);
  });
});

