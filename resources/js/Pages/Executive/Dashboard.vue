<script setup>
import StaffDashboard from '../Staff/Dashboard.vue';
</script>

<template>
    <StaffDashboard />
</template>

<style scoped>
.manager-shell {
    font-family: 'Sarabun', 'Noto Sans Thai', system-ui, sans-serif;
    font-synthesis-weight: none;
    letter-spacing: 0;
}

.manager-shell .sidebar {
    width: 232px;
}

.manager-shell .topbar {
    height: 52px;
    padding: 0 22px;
}

.manager-shell .content {
    padding: 22px;
}

.nav-item {
    width: 100%;
    border-top: 0;
    border-right: 0;
    border-bottom: 0;
    background: transparent;
    font-family: inherit;
    text-align: left;
}

.manager-menu-button {
    min-width: 40px;
    justify-content: center;
    border: 0;
    background: transparent;
    padding: 8px;
}

.manager-page-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-top: 2px;
}

.manager-title {
    color: var(--navy);
    font-size: 24px;
}

.manager-hero {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    gap: 20px;
    padding: 34px;
    border-radius: 16px;
    background: var(--navy);
    color: #fff;
}

.manager-hero-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(120px, 1fr));
    gap: 0;
}

.manager-metric {
    padding: 0 26px;
    border-left: 1px solid rgba(255, 255, 255, 0.14);
}

.manager-metric:first-child {
    padding-left: 0;
    border-left: 0;
}

.manager-label {
    color: rgba(255, 255, 255, 0.48);
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 8px;
}

.manager-value {
    color: #fff;
    font-size: 44px;
    font-weight: 800;
    line-height: 1;
}

.manager-value.green {
    color: #4ade80;
}

.manager-value.red {
    color: #fca5a5;
}

.manager-sub {
    color: rgba(255, 255, 255, 0.55);
    font-size: 12px;
    font-weight: 400;
    margin-top: 8px;
}

.manager-donut {
    width: 144px;
    height: 144px;
    justify-self: end;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: conic-gradient(#4ade80 0deg var(--pct), rgba(255, 255, 255, 0.12) var(--pct) 360deg);
}

.manager-donut-inner {
    width: 108px;
    height: 108px;
    display: grid;
    place-items: center;
    align-content: center;
    border-radius: 50%;
    background: var(--navy);
    text-align: center;
}

.manager-donut-inner strong {
    color: #fff;
    font-size: 26px;
    font-weight: 800;
    line-height: 1;
}

.manager-donut-inner span {
    color: rgba(255, 255, 255, 0.65);
    font-size: 11px;
    font-weight: 400;
    margin-top: 6px;
}

.manager-toolbar {
    flex-wrap: wrap;
    gap: 8px;
}

.manager-filter-block {
    flex: 0 0 auto;
}

.manager-filter-block .sel {
    width: 160px;
    height: 32px;
    padding: 4px 32px 4px 10px;
    font-size: 12px;
}

.manager-filter-block:nth-of-type(3) .sel {
    width: 170px;
}

.manager-filter-block:nth-of-type(4) .sel {
    width: 160px;
}

.manager-filter-block .btn {
    width: 32px;
    height: 32px;
    justify-content: center;
    padding: 0;
}

.manager-meta {
    display: flex;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
    justify-content: flex-end;
    margin-left: auto;
}

.manager-legend,
.risk-legend {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.manager-legend {
    color: var(--text3);
    font-size: 11px;
    font-weight: 700;
}

.risk-legend {
    color: var(--text3);
    font-size: 10px;
}

.legend-dot {
    width: 22px;
    height: 7px;
    display: inline-block;
    border-radius: 999px;
}

.legend-dot.pass {
    background: var(--green);
}

.legend-dot.fail {
    background: #fecaca;
}

.legend-dot.none {
    background: #e2e8f0;
}

.risk {
    display: inline-flex;
    align-items: center;
    min-height: 20px;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
}

.risk.high {
    background: var(--red-bg);
    color: var(--red);
}

.risk.mid {
    background: var(--yellow-bg);
    color: var(--yellow);
}

.risk.low {
    background: var(--green-bg);
    color: var(--green);
}

.manager-table-wrap {
    overflow-x: auto;
}

.manager-table {
    min-width: 760px;
}

.manager-dept-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 14px 18px;
}

.manager-dept-head {
    display: grid;
    grid-template-columns: 4px minmax(220px, 1.4fr) 128px minmax(160px, 1fr) 132px 20px;
    align-items: center;
    gap: 12px;
    padding: 0 16px 2px;
    color: var(--text3);
    font-size: 11px;
    font-weight: 700;
}

.manager-dept-head span:nth-child(3),
.manager-dept-head span:nth-child(5) {
    text-align: center;
}

.manager-empty-row {
    min-height: 76px;
    display: grid;
    grid-template-columns: 4px minmax(220px, 1.4fr) 128px minmax(160px, 1fr) 132px 20px;
    align-items: center;
    gap: 12px;
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    background: #fff;
    padding: 12px 16px;
}

.manager-empty-row::before {
    content: "";
    width: 4px;
    height: 36px;
    border-radius: 3px;
    background: #e2e8f0;
    flex-shrink: 0;
}

.manager-empty-row .manager-empty-message {
    grid-column: 2 / -1;
    display: flex;
    align-items: center;
    gap: 14px;
}

.problem-body {
    padding: 14px 18px 18px;
}

.problem-warning {
    border: 1px solid #fbbf24;
    border-radius: var(--r-lg);
    background: #fffbeb;
    color: #ea580c;
    padding: 10px 14px;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 14px;
}

.problem-empty {
    min-height: 120px;
    display: flex;
    align-items: center;
    gap: 14px;
    border: 1px dashed var(--border);
    border-radius: var(--r-lg);
    background: var(--bg);
    padding: 20px;
}

.idp-summary-card {
    border-left: 4px solid var(--teal);
    background: linear-gradient(135deg, #fff 60%, var(--teal-lt));
}

.idp-summary-row {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.idp-summary-main {
    flex: 1;
    min-width: 190px;
}

.idp-summary-number {
    display: flex;
    align-items: baseline;
    gap: 8px;
    flex-wrap: wrap;
}

.idp-summary-number span {
    color: var(--teal);
    font-size: 36px;
    font-weight: 800;
    line-height: 1;
}

.idp-summary-number em {
    color: var(--text3);
    font-size: 16px;
    font-style: normal;
    font-weight: 600;
}

.idp-summary-number b {
    border-radius: 20px;
    background: var(--teal);
    color: #fff;
    padding: 3px 10px;
    font-size: 13px;
    font-weight: 800;
}

.idp-summary-status {
    flex: 2;
    min-width: 280px;
}

.idp-status-bar {
    display: flex;
    height: 20px;
    overflow: hidden;
    border-radius: 6px;
    background: var(--border);
}

.idp-status-bar span {
    width: 0;
}

.idp-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 8px;
}

.idp-legend span {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
}

.idp-legend i {
    width: 10px;
    height: 10px;
    border-radius: 3px;
    display: inline-block;
}

.completed {
    background: #16a34a;
}

.in-progress {
    background: #0ea5a0;
}

.submitted {
    background: #fcd34d;
}

.draft {
    background: #fb923c;
}

.missing {
    background: #ef4444;
}

.idp-panel-body {
    padding: 0;
}

.idp-panel-body .manager-empty {
    min-height: 150px;
    border: 0;
    border-radius: 0;
}

.approval-table-body {
    padding: 0;
    overflow-x: auto;
}

.approval-table-body .manager-table {
    min-width: 860px;
}

.approval-table-body .score-col {
    width: 100px;
    text-align: center;
}

.approval-table-body .status-col {
    width: 118px;
}

.approval-table-body .action-col {
    width: 190px;
}

.table-empty-cell {
    min-height: 140px;
    display: flex;
    align-items: center;
    gap: 14px;
    background: var(--bg);
    padding: 22px;
}

.table-empty-cell.compact {
    min-height: 118px;
}

.tbl td:has(.table-empty-cell) {
    padding: 0;
}

.manager-empty {
    display: flex;
    gap: 14px;
    align-items: center;
    min-height: 140px;
    border: 1px dashed var(--border);
    border-radius: var(--r-lg);
    background: var(--bg);
    padding: 22px;
}

.manager-empty.compact {
    min-height: 118px;
}

.manager-empty-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--blue-lt);
    color: var(--blue);
    font-size: 22px;
    font-weight: 800;
    flex-shrink: 0;
}

.sv-tail {
    color: var(--text3);
    font-size: 14px;
}

.btn:disabled,
.sel:disabled {
    cursor: not-allowed;
    opacity: 1;
}

.btn-s:disabled,
.sel:disabled {
    background-color: #f8fafc;
    color: var(--text3);
    border-color: var(--border);
}

@media (max-width: 1100px) {
    .manager-hero,
    .manager-hero-grid {
        grid-template-columns: 1fr;
    }

    .manager-metric {
        padding: 0;
        border-left: 0;
    }

    .manager-donut {
        justify-self: start;
    }

    .manager-legend,
    .risk-legend {
        justify-content: flex-start;
    }
}

@media (max-width: 768px) {
    .manager-shell {
        height: auto;
        min-height: 100vh;
        flex-direction: column;
        overflow: visible;
    }

    .sidebar {
        position: static;
        width: 100%;
    }

    .main {
        min-height: 0;
    }

    .content {
        overflow: visible;
    }

    .g2,
    .g4 {
        grid-template-columns: 1fr;
    }

    .manager-page-head {
        align-items: flex-start;
        flex-direction: column;
    }

    .manager-hero {
        padding: 22px;
    }

    .manager-meta {
        margin-left: 0;
        justify-content: flex-start;
    }
}
</style>
