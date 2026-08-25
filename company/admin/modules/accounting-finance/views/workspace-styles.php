<style>
    .yovel-finance-workspace .yovel-finance-sticky-nav {
        position: sticky;
        top: 0;
        z-index: 30;
        background: var(--background);
    }

    .yovel-finance-workspace .yovel-finance-sticky-panel-header,
    .yovel-finance-workspace .yovel-finance-sticky-modal-header,
    .yovel-finance-workspace .yovel-finance-sticky-table-header {
        position: sticky;
        top: 0;
        z-index: 20;
    }

    .yovel-finance-workspace .yovel-finance-sticky-table-header {
        z-index: 10;
    }

    @media (min-width: 1280px) {
        .yovel-finance-workspace .yovel-hr-two-panel {
            grid-template-columns: minmax(0, 12fr) minmax(16rem, 8fr);
        }
    }
</style>
