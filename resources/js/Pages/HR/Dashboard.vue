<script setup>
import StaffDashboard from '../Staff/Dashboard.vue';
</script>

<template>
    <StaffDashboard />
</template>

<style scoped>
.shell {
    font-family: 'Sarabun', 'Noto Sans Thai', system-ui, sans-serif;
    font-synthesis-weight: none;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    letter-spacing: 0;
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

.hr-menu-button {
    min-width: 40px;
    justify-content: center;
    border: 0;
    background: transparent;
    padding: 8px;
}

.content .btn.btn-p {
    background: var(--blue);
    border: none;
    color: #fff;
}

.content .btn.btn-p:hover {
    background: #1d4ed8;
}

.content .btn.btn-t {
    background: var(--teal);
    border: none;
    color: #fff;
}

.content .btn.btn-t:hover {
    background: #0c8a85;
}

.content .btn.btn-s {
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text2);
}

.content .btn.btn-s:hover {
    background: var(--border);
}

.content .sec-t {
    font-weight: 800;
}

.content .sec-s,
.content .muted,
.content .cs,
.content .ss {
    font-weight: 400;
}

.content .ct {
    font-weight: 700;
}

.content .sl,
.content .lbl,
.content .tbl th {
    font-weight: 700;
}

.content .tbl td {
    font-weight: 400;
}

.content .fw8 {
    font-weight: 700;
}

.hr-empty {
    display: flex;
    gap: 14px;
    align-items: center;
    min-height: 150px;
    border: 1px dashed var(--border);
    border-radius: var(--r-lg);
    background: var(--bg);
    padding: 22px;
}

.hr-empty-icon {
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

.hr-empty.compact {
    min-height: 118px;
}

.cycle-picker {
    display: flex;
    align-items: stretch;
    gap: 10px;
    flex-wrap: wrap;
}

.cycle-empty {
    min-height: 62px;
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 6px 0;
}

.hr-page-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
}

.hr-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}

.hr-table-wrap {
    overflow-x: auto;
}

.hr-fixed-table {
    min-width: 760px;
}

.catalog-table {
    min-width: 820px;
}

.oc {
    color: var(--orange);
}

.table-empty-cell {
    min-height: 118px;
    display: flex;
    align-items: center;
    gap: 14px;
    background: var(--bg);
    padding: 20px;
}

.tbl td:has(.table-empty-cell) {
    padding: 0;
}

.hr-card-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 12px 16px;
    border-top: 1px solid var(--border);
    background: var(--bg);
}

.sv-tail {
    color: var(--text3);
    font-size: 14px;
}

.expect-tabs {
    display: flex;
    align-items: center;
    gap: 0;
    flex-wrap: wrap;
    border-bottom: 2px solid var(--border);
}

.expect-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 20px;
    border: 0;
    border-bottom: 3px solid transparent;
    border-radius: 0;
    margin-bottom: -2px;
    background: transparent;
    color: var(--text3);
    font: inherit;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.expect-tab.on {
    border-bottom-color: var(--teal);
    color: var(--teal);
}

.expect-layout {
    display: grid;
    grid-template-columns: 310px minmax(0, 1fr);
    gap: 14px;
    align-items: start;
}

.ml-auto {
    margin-left: auto;
}

.btn:disabled,
.expect-tab:disabled,
.sel:disabled,
.inp:disabled {
    cursor: not-allowed;
    opacity: 1;
}

.btn-p:disabled {
    border: 1px solid var(--blue-md);
    background: var(--blue-lt);
    color: var(--blue);
}

.btn-t:disabled {
    border: 1px solid var(--teal-md);
    background: var(--teal-lt);
    color: var(--teal);
}

.btn-s:disabled,
.expect-tab:disabled,
.sel:disabled,
.inp:disabled {
    background-color: #f8fafc;
    color: var(--text3);
    border-color: var(--border);
}

.position-hero {
    min-height: 138px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 20px;
    align-items: end;
    padding: 24px 26px;
    border-radius: 14px;
    background: #102f5d;
    color: #f8fbff;
    box-shadow: 0 16px 34px rgba(15, 45, 91, 0.18);
}

.position-kicker {
    color: rgba(248, 251, 255, 0.58);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.position-title {
    color: #f8fbff;
    font-size: 26px;
}

.position-sub {
    color: rgba(248, 251, 255, 0.68);
    max-width: 760px;
    line-height: 1.65;
}

.position-hero-metrics {
    display: grid;
    grid-template-columns: repeat(2, 120px);
    gap: 10px;
}

.position-hero-metrics div {
    padding: 14px;
    border: 1px solid rgba(248, 251, 255, 0.16);
    border-radius: 10px;
    background: rgba(248, 251, 255, 0.08);
}

.position-hero-metrics span {
    display: block;
    font-size: 30px;
    font-weight: 800;
    line-height: 1;
}

.position-hero-metrics small {
    display: block;
    color: rgba(248, 251, 255, 0.64);
    font-size: 11px;
    font-weight: 700;
    margin-top: 6px;
}

.position-scope {
    display: grid;
    grid-template-columns: minmax(320px, 1fr) minmax(360px, 1fr);
    gap: 14px;
    padding: 14px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: #fff;
    box-shadow: var(--sh);
}

.position-scope-label {
    color: var(--text3);
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 8px;
}

.position-segments {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.position-segments button {
    min-height: 36px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: #fff;
    color: var(--text2);
    padding: 0 14px;
    font: inherit;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.position-segments button.active {
    background: var(--navy);
    border-color: var(--navy);
    color: #fff;
}

.position-empty-inline {
    min-height: 36px;
    display: inline-flex;
    align-items: center;
    border: 1px dashed var(--border);
    border-radius: 999px;
    background: var(--bg);
    color: var(--text3);
    padding: 0 14px;
    font-size: 12px;
    font-weight: 700;
}

.position-picker {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    align-items: end;
}

.position-picker .fg:only-child {
    grid-column: 1 / -1;
}

.position-board {
    display: grid;
    grid-template-columns: 1.35fr repeat(2, minmax(180px, 0.65fr));
    gap: 14px;
}

.position-card {
    min-height: 104px;
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: #fff;
    box-shadow: var(--sh);
}

.position-card.selected {
    border-color: var(--blue-md);
    background: linear-gradient(180deg, #fff 0%, var(--blue-lt) 100%);
}

.position-card-label {
    color: var(--text3);
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 8px;
}

.position-card-title {
    color: var(--text);
    font-size: 24px;
    font-weight: 800;
    line-height: 1.2;
}

.position-card-title.ok {
    color: var(--green);
}

.position-card-title.warn {
    color: var(--yellow);
}

.position-card-sub {
    color: var(--text3);
    font-size: 12px;
    font-weight: 600;
    margin-top: 7px;
}

.position-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.25fr) minmax(360px, 0.75fr);
    gap: 14px;
    align-items: start;
}

.position-panel {
    border: 1px solid var(--border);
    border-radius: 12px;
    background: #fff;
    box-shadow: var(--sh);
    overflow: hidden;
}

.position-panel-head {
    min-height: 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    background: #fff;
}

.assigned-list {
    display: grid;
    gap: 10px;
    padding: 14px;
}

.assigned-empty {
    min-height: 220px;
    display: grid;
    place-items: center;
    align-content: center;
    gap: 8px;
    border: 1px dashed var(--border);
    border-radius: 10px;
    background: var(--bg);
    text-align: center;
}

.assigned-empty-icon {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    background: #fff;
    box-shadow: var(--sh);
    font-size: 22px;
}

.dictionary {
    position: sticky;
    top: 14px;
}

.dictionary-tools {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 132px;
    gap: 10px;
    padding: 14px;
    border-bottom: 1px solid var(--border);
    background: var(--bg);
}

.dictionary-list {
    display: grid;
    gap: 8px;
    max-height: 560px;
    overflow-y: auto;
    padding: 14px;
}

.dictionary-empty {
    min-height: 340px;
}

.truncate-2 {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
}

@media (max-width: 1100px) {
    .position-layout,
    .position-scope,
    .position-board,
    .position-hero,
    .expect-layout {
        grid-template-columns: 1fr;
    }

    .dictionary {
        position: static;
    }

    .position-hero-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

}

@media (max-width: 768px) {
    .shell {
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

    .g3 {
        grid-template-columns: 1fr;
    }

    .g2,
    .g4 {
        grid-template-columns: 1fr;
    }

    .hr-page-head {
        align-items: flex-start;
        flex-direction: column;
    }

    .hr-actions {
        justify-content: flex-start;
    }

    .position-picker,
    .dictionary-tools {
        grid-template-columns: 1fr;
    }

    .position-hero {
        padding: 20px;
    }

    .position-title {
        font-size: 22px;
    }

}
</style>
