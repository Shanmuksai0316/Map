#!/usr/bin/env node

/**
 * Generate Comprehensive UI/UX Test Status Report
 * Analyzes test results, screenshots, and generates a detailed report
 */

const fs = require('fs');
const path = require('path');

const SCREENSHOTS_DIR = path.join(__dirname, '../test-results/screenshots');
const REPORTS_DIR = path.join(__dirname, '../test-results/reports');
const TEST_LOG = path.join(REPORTS_DIR, 'test-execution.log');

// Staff roles and their test phone numbers
const STAFF_ROLES = [
  { 
    role: 'Campus Manager', 
    phone: '8888888888', 
    screens: ['Dashboard', 'Student Management', 'Gate Pass', 'Reports', 'Notices', 'Room Allocation', 'Profile'] 
  },
  { 
    role: 'Rector', 
    phone: '9999999999', 
    screens: ['Dashboard', 'Student Insights', 'Approvals', 'Profile'] 
  },
  { 
    role: 'Warden', 
    phone: '5555555555', 
    screens: ['Dashboard', 'Attendance', 'Checklist', 'Requests', 'Students'] 
  },
  { 
    role: 'Guard', 
    phone: '4444444444', 
    screens: ['Dashboard', 'Scan QR', 'Gate Pass'] 
  },
  { 
    role: 'HK Supervisor', 
    phone: '7777777777', 
    screens: ['Dashboard', 'Requests', 'Checklists'] 
  },
  { 
    role: 'RM Supervisor', 
    phone: '6666666666', 
    screens: ['Dashboard', 'Requests', 'Checklist'] 
  },
  { 
    role: 'Laundry Manager', 
    phone: '3333333333', 
    screens: ['Dashboard', 'Scan QR', 'Gate Pass'] 
  },
  { 
    role: 'Sports Manager', 
    phone: '2222222222', 
    screens: ['Dashboard', 'Blockouts', 'Monitoring', 'Checklist'] 
  },
];

// Get all screenshots
function getScreenshots() {
  if (!fs.existsSync(SCREENSHOTS_DIR)) {
    return [];
  }
  return fs.readdirSync(SCREENSHOTS_DIR)
    .filter(file => file.endsWith('.png'))
    .sort();
}

// Parse test log for errors
function parseTestLog() {
  if (!fs.existsSync(TEST_LOG)) {
    return { errors: [], warnings: [], info: [] };
  }
  
  const logContent = fs.readFileSync(TEST_LOG, 'utf-8');
  const errors = [];
  const warnings = [];
  const info = [];
  
  // Extract errors
  const errorLines = logContent.split('\n').filter(line => 
    /ERROR|FAIL|Error|Failed|Exception/i.test(line)
  );
  errors.push(...errorLines);
  
  // Extract warnings
  const warningLines = logContent.split('\n').filter(line => 
    /WARN|Warning/i.test(line)
  );
  warnings.push(...warningLines);
  
  // Extract info
  const infoLines = logContent.split('\n').filter(line => 
    /INFO|PASS|Success/i.test(line)
  );
  info.push(...infoLines);
  
  return { errors, warnings, info };
}

// Analyze screenshots
function analyzeScreenshots() {
  const screenshots = getScreenshots();
  const analysis = {
    total: screenshots.length,
    byRole: {},
    missing: [],
    issues: [],
  };
  
  STAFF_ROLES.forEach(role => {
    const roleKey = role.role.toLowerCase().replace(/\s+/g, '-');
    const roleScreenshots = screenshots.filter(s => 
      s.toLowerCase().includes(roleKey)
    );
    
    analysis.byRole[role.role] = {
      count: roleScreenshots.length,
      files: roleScreenshots,
      expected: role.screens.length,
      coverage: role.screens.length > 0 
        ? Math.round((roleScreenshots.length / role.screens.length) * 100)
        : 0,
    };
    
    // Check for missing screenshots
    role.screens.forEach(screen => {
      const screenKey = screen.toLowerCase().replace(/\s+/g, '-');
      const screenShot = roleScreenshots.find(s => 
        s.toLowerCase().includes(screenKey)
      );
      if (!screenShot) {
        analysis.missing.push(`${role.role} - ${screen}`);
      }
    });
    
    // Check for issues (low coverage)
    if (analysis.byRole[role.role].coverage < 50) {
      analysis.issues.push(`${role.role}: Low coverage (${analysis.byRole[role.role].coverage}%)`);
    }
  });
  
  return analysis;
}

// Generate report
function generateReport() {
  const screenshots = getScreenshots();
  const screenshotAnalysis = analyzeScreenshots();
  const testLog = parseTestLog();
  const timestamp = new Date().toISOString();
  
  let report = `# Staff App UI/UX Test Status Report\n\n`;
  report += `**Generated:** ${timestamp}\n`;
  report += `**Test Framework:** Maestro E2E Testing\n\n`;
  report += `---\n\n`;
  
  // Executive Summary
  report += `## 📊 Executive Summary\n\n`;
  report += `| Metric | Value |\n`;
  report += `|--------|-------|\n`;
  report += `| Total Screenshots Captured | ${screenshots.length} |\n`;
  report += `| Roles Tested | ${STAFF_ROLES.length} |\n`;
  report += `| Errors Found | ${testLog.errors.length} |\n`;
  report += `| Warnings | ${testLog.warnings.length} |\n`;
  report += `| Missing Screenshots | ${screenshotAnalysis.missing.length} |\n`;
  
  const totalExpected = STAFF_ROLES.reduce((sum, role) => sum + role.screens.length, 0);
  const overallCoverage = totalExpected > 0 
    ? Math.round((screenshots.length / totalExpected) * 100)
    : 0;
  report += `| Overall Coverage | ${overallCoverage}% |\n`;
  report += `| Overall Status | ${overallCoverage >= 80 ? '✅ PASS' : overallCoverage >= 50 ? '⚠️ PARTIAL' : '❌ FAIL'} |\n\n`;
  
  // Role-by-Role Status
  report += `## 👥 Role-by-Role Status\n\n`;
  
  STAFF_ROLES.forEach(role => {
    const roleData = screenshotAnalysis.byRole[role.role] || { 
      count: 0, 
      files: [], 
      expected: role.screens.length,
      coverage: 0 
    };
    const status = roleData.coverage >= 80 ? '✅' : roleData.coverage >= 50 ? '⚠️' : '❌';
    
    report += `### ${status} ${role.role}\n\n`;
    report += `- **Phone:** ${role.phone}\n`;
    report += `- **Screenshots Captured:** ${roleData.count} / ${roleData.expected}\n`;
    report += `- **Coverage:** ${roleData.coverage}%\n`;
    report += `- **Status:** ${roleData.count > 0 ? 'Tested' : 'Not Tested'}\n\n`;
    
    if (roleData.files.length > 0) {
      report += `**Screenshots Captured:**\n`;
      roleData.files.forEach(file => {
        report += `- ✅ \`${file}\`\n`;
      });
      report += `\n`;
    }
    
    report += `**Screens Tested:**\n`;
    role.screens.forEach(screen => {
      const screenKey = screen.toLowerCase().replace(/\s+/g, '-');
      const hasScreenshot = roleData.files.some(f => 
        f.toLowerCase().includes(screenKey)
      );
      report += `- ${hasScreenshot ? '✅' : '❌'} ${screen}\n`;
    });
    report += `\n`;
  });
  
  // Issues Found
  report += `## 🐛 Issues Found\n\n`;
  
  if (testLog.errors.length > 0) {
    report += `### Errors (${testLog.errors.length})\n\n`;
    const uniqueErrors = [...new Set(testLog.errors)];
    uniqueErrors.slice(0, 20).forEach((error, index) => {
      report += `${index + 1}. ${error.substring(0, 200)}\n`;
    });
    if (uniqueErrors.length > 20) {
      report += `\n... and ${uniqueErrors.length - 20} more errors\n`;
    }
    report += `\n`;
  } else {
    report += `### Errors\n\n`;
    report += `✅ No errors found\n\n`;
  }
  
  if (screenshotAnalysis.missing.length > 0) {
    report += `### Missing Screenshots (${screenshotAnalysis.missing.length})\n\n`;
    screenshotAnalysis.missing.forEach((missing, index) => {
      report += `${index + 1}. ${missing}\n`;
    });
    report += `\n`;
  } else {
    report += `### Missing Screenshots\n\n`;
    report += `✅ All expected screenshots captured\n\n`;
  }
  
  if (screenshotAnalysis.issues.length > 0) {
    report += `### Coverage Issues\n\n`;
    screenshotAnalysis.issues.forEach((issue, index) => {
      report += `${index + 1}. ${issue}\n`;
    });
    report += `\n`;
  }
  
  // Screenshot Gallery
  report += `## 📸 Screenshot Gallery\n\n`;
  report += `Total screenshots: ${screenshots.length}\n\n`;
  
  if (screenshots.length > 0) {
    report += `### All Screenshots\n\n`;
    screenshots.forEach((file, index) => {
      report += `${index + 1}. \`${file}\`\n`;
    });
    report += `\n`;
  }
  
  // Test Coverage Table
  report += `## 📈 Test Coverage\n\n`;
  report += `| Role | Screenshots | Expected | Coverage | Status |\n`;
  report += `|------|-------------|----------|----------|--------|\n`;
  
  STAFF_ROLES.forEach(role => {
    const roleData = screenshotAnalysis.byRole[role.role] || { 
      count: 0, 
      expected: role.screens.length,
      coverage: 0 
    };
    const status = roleData.coverage >= 80 ? '✅' : roleData.coverage >= 50 ? '⚠️' : '❌';
    report += `| ${role.role} | ${roleData.count} | ${roleData.expected} | ${roleData.coverage}% | ${status} |\n`;
  });
  
  report += `\n`;
  
  // Recommendations
  report += `## 💡 Recommendations\n\n`;
  
  if (screenshotAnalysis.missing.length > 0) {
    report += `### 1. Retest Missing Screens\n`;
    report += `The following screens need to be tested:\n\n`;
    screenshotAnalysis.missing.forEach(missing => {
      report += `- ${missing}\n`;
    });
    report += `\n`;
  }
  
  if (testLog.errors.length > 0) {
    report += `### 2. Fix Errors\n`;
    report += `The following errors need to be addressed:\n\n`;
    const uniqueErrors = [...new Set(testLog.errors)];
    uniqueErrors.slice(0, 10).forEach((error, index) => {
      report += `${index + 1}. ${error.substring(0, 150)}\n`;
    });
    report += `\n`;
  }
  
  report += `### 3. Review Screenshots\n`;
  report += `- Check all screenshots in \`test-results/screenshots/\`\n`;
  report += `- Verify UI/UX consistency across all roles\n`;
  report += `- Check for visual bugs or layout issues\n\n`;
  
  report += `### 4. Verify Functionality\n`;
  report += `- Test all interactive elements (buttons, forms, navigation)\n`;
  report += `- Verify API integrations are working\n`;
  report += `- Check error handling and loading states\n\n`;
  
  // Next Steps
  report += `## 🚀 Next Steps\n\n`;
  
  if (screenshotAnalysis.missing.length > 0) {
    report += `1. **Retest Missing Screens:** Run tests again for missing screens\n`;
  }
  
  if (testLog.errors.length > 0) {
    report += `2. **Fix Errors:** Address the errors found in test execution\n`;
  }
  
  report += `3. **Review Screenshots:** Check all screenshots in \`test-results/screenshots/\`\n`;
  report += `4. **Verify UI/UX:** Manually review screenshots for UI/UX issues\n`;
  report += `5. **Update Report:** Re-run tests and regenerate report\n\n`;
  
  // Footer
  report += `---\n\n`;
  report += `**Report Generated:** ${timestamp}\n`;
  report += `**Screenshots Location:** \`test-results/screenshots/\`\n`;
  report += `**Test Log:** \`test-results/reports/test-execution.log\`\n`;
  report += `**Test Script:** \`maestro/e2e-staff-all-roles-with-screenshots.yaml\`\n`;
  
  return report;
}

// Main execution
function main() {
  console.log('Generating UI/UX Test Status Report...');
  
  // Ensure reports directory exists
  if (!fs.existsSync(REPORTS_DIR)) {
    fs.mkdirSync(REPORTS_DIR, { recursive: true });
  }
  
  // Ensure screenshots directory exists
  if (!fs.existsSync(SCREENSHOTS_DIR)) {
    fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });
  }
  
  // Generate report
  const report = generateReport();
  
  // Write report
  const reportPath = path.join(REPORTS_DIR, 'ui-test-status-report.md');
  fs.writeFileSync(reportPath, report, 'utf-8');
  
  console.log(`✅ Report generated: ${reportPath}`);
  console.log(`📊 Report preview:`);
  console.log(report.substring(0, 800) + '...\n');
}

main();
