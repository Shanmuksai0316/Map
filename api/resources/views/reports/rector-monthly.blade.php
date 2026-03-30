<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rector Monthly Approval Report - {{ date('F Y', mktime(0, 0, 0, $month, 1, $year)) }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #2563eb;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .summary {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .metric {
            text-align: center;
        }
        .metric-value {
            font-size: 28px;
            font-weight: bold;
            color: #2563eb;
            display: block;
        }
        .metric-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        .sla-compliance {
            background: #dcfce7;
            color: #166534;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            margin-top: 20px;
        }
        .sla-compliance .value {
            font-size: 32px;
            font-weight: bold;
            display: block;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px 12px;
            text-align: left;
        }
        th {
            background: #f8fafc;
            font-weight: bold;
            color: #374151;
        }
        tr:nth-child(even) {
            background: #f9fafb;
        }
        .status-approved {
            color: #166534;
            font-weight: bold;
        }
        .status-rejected, .status-declined {
            color: #dc2626;
            font-weight: bold;
        }
        .sla-breach {
            background: #fef2f2;
        }
        .sla-breach td {
            color: #dc2626;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        .page-break {
            page-break-before: always;
        }
        @media print {
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Rector Monthly Approval Report</h1>
        <p><strong>Period:</strong> {{ date('F Y', mktime(0, 0, 0, $month, 1, $year)) }}</p>
        <p><strong>Generated:</strong> {{ $generated_at->format('d M Y H:i') }}</p>
    </div>

    <div class="summary">
        <h2 style="margin-top: 0; color: #374151;">Summary Statistics</h2>

        <div class="summary-grid">
            <div class="metric">
                <span class="metric-value">{{ $data['summary']['total_decisions'] }}</span>
                <div class="metric-label">Total Decisions</div>
            </div>

            <div class="metric">
                <span class="metric-value">{{ $data['summary']['approved'] }}</span>
                <div class="metric-label">Approved</div>
            </div>

            <div class="metric">
                <span class="metric-value">{{ $data['summary']['rejected'] }}</span>
                <div class="metric-label">Rejected/Declined</div>
            </div>

            <div class="metric">
                <span class="metric-value">{{ $data['summary']['sla_breached'] }}</span>
                <div class="metric-label">SLA Breaches</div>
            </div>
        </div>

        <div class="sla-compliance">
            <span class="value">{{ $data['summary']['sla_compliance_percentage'] }}%</span>
            <div>SLA Compliance Rate</div>
        </div>
    </div>

    <h2>Detailed Decisions</h2>

    <table>
        <thead>
            <tr>
                <th>Type</th>
                <th>Request ID</th>
                <th>Student</th>
                <th>Hostel</th>
                <th>Decision</th>
                <th>Submitted</th>
                <th>Decided</th>
                <th>Decided By</th>
                <th>SLA Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['decisions'] as $decision)
            <tr class="{{ $decision['sla_breached'] ? 'sla-breach' : '' }}">
                <td>{{ $decision['type'] }}</td>
                <td>{{ $decision['unique_id'] }}</td>
                <td>{{ $decision['student_name'] }}</td>
                <td>{{ $decision['hostel_name'] }}</td>
                <td class="status-{{ strtolower($decision['status']) }}">
                    {{ ucfirst($decision['status']) }}
                </td>
                <td>{{ \Carbon\Carbon::parse($decision['submitted_at'])->format('d/m/y H:i') }}</td>
                <td>{{ \Carbon\Carbon::parse($decision['decided_at'])->format('d/m/y H:i') }}</td>
                <td>{{ $decision['decided_by'] }}</td>
                <td>
                    @if($decision['sla_breached'])
                        Breached (+{{ number_format($decision['sla_breach_hours'], 1) }}h)
                    @else
                        Within SLA
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>This report was generated automatically by the MAP Hostel Management System.</p>
        <p>Confidential - For Rector and Campus Management use only.</p>
    </div>
</body>
</html>
