<?php
declare(strict_types=1);

function yovel_admin_sales_crm_dashboard_unavailable_summary(string $key, string $label, string $contract): array
{
    return [
        'key' => $key,
        'label' => $label,
        'value' => null,
        'availability' => 'UNAVAILABLE_DEPENDENCY',
        'href' => '',
        'dependency' => $contract,
    ];
}

function yovel_admin_sales_crm_dashboard_activity_label(array $values, string $module, string $recordKey): string
{
    foreach (['lead_name', 'prospect_name', 'appointment_code', 'note_code', 'campaign_name', 'campaign_code', 'schedule_code', 'record_type'] as $field) {
        $value = trim((string) ($values[$field] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }
    $moduleLabels = [
        'project_company_sales_lead' => 'Lead',
        'project_company_sales_prospect' => 'Prospect',
        'project_company_sales_appointment' => 'Appointment',
        'project_company_sales_crm_note' => 'CRM Note',
        'project_company_sales_campaign' => 'Campaign',
        'project_company_sales_campaign_email_schedule' => 'Campaign email schedule',
        'project_company_sales_crm_settings' => 'Sales / CRM settings',
        'project_company_sales_crm_preference' => 'Sales / CRM workspace preference',
        'project_company_form_schema' => 'Sales / CRM form layout',
    ];
    $suffix = $recordKey !== '' ? ' ' . substr($recordKey, 0, 8) : '';
    return ($moduleLabels[$module] ?? 'Sales / CRM record') . $suffix;
}

function yovel_admin_sales_crm_dashboard_data(array $company, array $admin): array
{
    $authorized = yovel_admin_sales_crm_authorized_admin($company, $admin);
    $workspace = yovel_admin_sales_crm_workspace($company, $authorized);
    $access = is_array($workspace['access'] ?? null) ? $workspace['access'] : ['crm' => false, 'selling' => false, 'manage_settings' => false];
    if (empty($access['crm']) && empty($access['selling'])) {
        throw new InvalidArgumentException('This administrator does not have access to the Sales / CRM dashboard.');
    }

    $db = bx_db();
    $companyKey = (string) $company['company_key'];
    $companyHash = (string) $company['company_key_hash'];
    $canCrm = !empty($access['crm']);

    $summary = [];
    $queue = [];
    $activity = [];
    $alerts = [];
    $leadCount = 0;
    $campaignCount = 0;
    $staleLeadCount = 0;

    if ($canCrm) {
        $leadSummary = $db->GetRow(
            "SELECT COUNT(*) AS lead_count, COALESCE(SUM(estimated_value), 0.00) AS pipeline_value
             FROM project_company_sales_lead
             WHERE company_key_hash = ? AND lead_status IN ('OPEN', 'QUALIFIED')",
            [$companyHash]
        );
        $leadCount = (int) ($leadSummary['lead_count'] ?? 0);
        $pipelineValue = yovel_admin_sales_campaign_decimal_readback($leadSummary['pipeline_value'] ?? '0.00');
        $campaignCount = (int) $db->GetOne(
            "SELECT COUNT(*) FROM project_company_sales_campaign
             WHERE company_key_hash = ? AND campaign_status = 'ACTIVE'",
            [$companyHash]
        );
        $summary[] = ['key' => 'open-leads', 'label' => 'Open and qualified leads', 'value' => $leadCount, 'availability' => 'AVAILABLE', 'href' => './?view=sales-crm&section=leads'];
        $summary[] = ['key' => 'pipeline-value', 'label' => 'Expected pipeline value', 'value' => $pipelineValue, 'availability' => 'AVAILABLE', 'href' => './?view=sales-crm&section=leads'];
        $summary[] = ['key' => 'active-campaigns', 'label' => 'Active campaigns', 'value' => $campaignCount, 'availability' => 'AVAILABLE', 'href' => './?view=sales-crm&section=campaigns'];

        $staleLeads = $db->GetAll(
            "SELECT lead_key, lead_code, lead_name, lead_status, next_contact_date, updated_at
             FROM project_company_sales_lead
             WHERE company_key_hash = ?
               AND lead_status IN ('OPEN', 'QUALIFIED')
               AND (
                    (next_contact_date IS NOT NULL AND next_contact_date < UTC_DATE())
                    OR (next_contact_date IS NULL AND updated_at < UTC_TIMESTAMP() - INTERVAL 7 DAY)
               )
             ORDER BY COALESCE(next_contact_date, DATE(updated_at)) ASC, updated_at ASC, lead_key ASC
             LIMIT 6",
            [$companyHash]
        );
        foreach (is_array($staleLeads) ? $staleLeads : [] as $lead) {
            $queue[] = [
                'key' => 'stale-lead:' . (string) $lead['lead_key'],
                'label' => (string) $lead['lead_name'],
                'status' => 'STALE_LEAD',
                'href' => './?view=sales-crm&section=leads&edit=' . rawurlencode((string) $lead['lead_key']),
                'detail' => (string) $lead['lead_code'] . ' · ' . (string) $lead['lead_status'],
                'sort_at' => (string) ($lead['next_contact_date'] ?: $lead['updated_at']),
            ];
        }
        $staleLeadCount = count(is_array($staleLeads) ? $staleLeads : []);

        $endingCampaigns = $db->GetAll(
            "SELECT campaign_key, campaign_code, campaign_name, campaign_status, end_date
             FROM project_company_sales_campaign
             WHERE company_key_hash = ? AND campaign_status = 'ACTIVE'
               AND end_date IS NOT NULL AND end_date BETWEEN UTC_DATE() AND UTC_DATE() + INTERVAL 7 DAY
             ORDER BY end_date ASC, campaign_key ASC
             LIMIT 4",
            [$companyHash]
        );
        foreach (is_array($endingCampaigns) ? $endingCampaigns : [] as $campaign) {
            $queue[] = [
                'key' => 'ending-campaign:' . (string) $campaign['campaign_key'],
                'label' => (string) $campaign['campaign_name'],
                'status' => 'ENDING_SOON',
                'href' => './?view=sales-crm&section=campaigns&edit=' . rawurlencode((string) $campaign['campaign_key']),
                'detail' => (string) $campaign['campaign_code'] . ' · ends ' . (string) $campaign['end_date'],
                'sort_at' => (string) $campaign['end_date'],
            ];
        }
        usort($queue, static fn (array $left, array $right): int => strcmp((string) $left['sort_at'], (string) $right['sort_at']) ?: strcmp((string) $left['key'], (string) $right['key']));
        $queue = array_slice($queue, 0, 8);

        if ($staleLeadCount > 0) {
            $alerts[] = ['key' => 'stale-leads', 'severity' => 'WARNING', 'label' => $staleLeadCount . ' lead' . ($staleLeadCount === 1 ? '' : 's') . ' need follow-up', 'href' => './?view=sales-crm&section=leads'];
        }
        if ($campaignCount === 0) {
            $alerts[] = ['key' => 'campaign-readiness', 'severity' => 'INFO', 'label' => 'No active campaign is currently driving attribution.', 'href' => './?view=sales-crm&section=campaigns'];
        }
    } else {
        $summary[] = yovel_admin_sales_crm_dashboard_unavailable_summary('open-leads', 'Open and qualified leads', 'sales.lead.summary.v1');
        $summary[] = yovel_admin_sales_crm_dashboard_unavailable_summary('pipeline-value', 'Expected pipeline value', 'sales.lead.summary.v1');
        $summary[] = yovel_admin_sales_crm_dashboard_unavailable_summary('active-campaigns', 'Active campaigns', 'sales.campaign.summary.v1');
        $alerts[] = ['key' => 'crm-access', 'severity' => 'WARNING', 'label' => 'CRM metrics are restricted for this administrator.', 'href' => ''];
    }

    $summary[] = yovel_admin_sales_crm_dashboard_unavailable_summary('qualified-opportunities', 'Qualified opportunities', 'sales.opportunity.summary.v1');
    $summary[] = yovel_admin_sales_crm_dashboard_unavailable_summary('open-quotations', 'Open quotations', 'sales.quotation.summary.v1');
    $summary[] = yovel_admin_sales_crm_dashboard_unavailable_summary('active-sales-orders', 'Active sales orders', 'sales.order.summary.v1');

    $auditModules = [
        'project_company_sales_lead',
        'project_company_sales_prospect',
        'project_company_sales_appointment',
        'project_company_sales_crm_note',
        'project_company_sales_campaign',
        'project_company_sales_campaign_email_schedule',
        'project_company_sales_crm_settings',
        'project_company_sales_crm_preference',
        'project_company_form_schema',
    ];
    $auditRows = $db->GetAll(
        "SELECT audit.audit_key, audit.action, audit.module, audit.record_key, audit.new_values,
                audit.created_at, COALESCE(admins.admin_name, admins.admin_login, 'Company administrator') AS actor_label
         FROM builder_audit_log audit
         INNER JOIN project_company_admin admins
            ON admins.admin_key = JSON_UNQUOTE(JSON_EXTRACT(audit.new_values, '$.admin_key'))
           AND admins.company_key_hash = ?
           AND admins.admin_status = 'ACTIVE'
         WHERE audit.module IN (?, ?, ?, ?, ?, ?, ?, ?, ?)
           AND JSON_VALID(audit.new_values) = 1
           AND (
                JSON_UNQUOTE(JSON_EXTRACT(audit.new_values, '$.company_key')) = ?
                OR JSON_UNQUOTE(JSON_EXTRACT(audit.new_values, '$.company_name')) = ?
           )
         ORDER BY audit.created_at DESC, audit.x_id DESC
         LIMIT 8",
        array_merge([$companyHash], $auditModules, [$companyKey, (string) $company['company_name']])
    );
    foreach (is_array($auditRows) ? $auditRows : [] as $audit) {
        $values = json_decode((string) ($audit['new_values'] ?? ''), true);
        $values = is_array($values) ? $values : [];
        $activity[] = [
            'key' => (string) $audit['audit_key'],
            'action' => (string) $audit['action'],
            'record_label' => yovel_admin_sales_crm_dashboard_activity_label($values, (string) $audit['module'], (string) ($audit['record_key'] ?? '')),
            'actor_label' => (string) $audit['actor_label'],
            'occurred_at' => (string) $audit['created_at'],
            'status' => (string) ($values['lead_status'] ?? $values['campaign_status'] ?? $values['send_status'] ?? ''),
        ];
    }

    $settings = is_array($workspace['settings'] ?? null) ? $workspace['settings'] : yovel_admin_sales_crm_settings_defaults();
    $activeSchemaCount = (int) $db->GetOne(
        "SELECT COUNT(*) FROM project_company_form_schema
         WHERE company_key_hash = ? AND module_code = 'sales-crm' AND schema_status = 'ACTIVE'",
        [$companyHash]
    );
    $setup = [
        ['key' => 'configure-access', 'label' => 'Configure Sales access', 'complete' => (int) ($settings['settings_version'] ?? 0) > 0, 'href' => './?view=sales-crm&section=dashboard&modal=settings'],
        ['key' => 'capture-lead', 'label' => 'Capture a Lead', 'complete' => $canCrm && $leadCount > 0, 'href' => './?view=sales-crm&section=leads'],
        ['key' => 'plan-campaign', 'label' => 'Plan a Campaign', 'complete' => $canCrm && $campaignCount > 0, 'href' => './?view=sales-crm&section=campaigns'],
        ['key' => 'publish-form', 'label' => 'Review Form Builder layouts', 'complete' => $activeSchemaCount > 0, 'href' => './?view=sales-crm&section=leads#yovel-sales-form-builder'],
    ];

    $shortcuts = [['key' => 'dashboard', 'label' => 'Sales dashboard', 'href' => './?view=sales-crm&section=dashboard', 'available' => true, 'availability' => 'AVAILABLE']];
    if ($canCrm) {
        $shortcuts[] = ['key' => 'leads', 'label' => 'Leads', 'href' => './?view=sales-crm&section=leads', 'available' => true, 'availability' => 'AVAILABLE'];
        $shortcuts[] = ['key' => 'prospects', 'label' => 'Prospects', 'href' => './?view=sales-crm&section=prospects', 'available' => true, 'availability' => 'AVAILABLE'];
        $shortcuts[] = ['key' => 'appointments', 'label' => 'Appointments', 'href' => './?view=sales-crm&section=appointments', 'available' => true, 'availability' => 'AVAILABLE'];
        $shortcuts[] = ['key' => 'campaigns', 'label' => 'Campaigns', 'href' => './?view=sales-crm&section=campaigns', 'available' => true, 'availability' => 'AVAILABLE'];
        $shortcuts[] = ['key' => 'form-builder', 'label' => 'Form Builder', 'href' => './?view=sales-crm&section=leads#yovel-sales-form-builder', 'available' => true, 'availability' => 'AVAILABLE'];
    }
    if (!empty($access['manage_settings'])) {
        $shortcuts[] = ['key' => 'settings', 'label' => 'Sales settings', 'href' => './?view=sales-crm&section=dashboard&modal=settings', 'available' => true, 'availability' => 'AVAILABLE'];
    }

    $directories = [
        ['group' => 'CRM Masters', 'items' => [
            ['key' => 'master-leads', 'label' => 'Leads', 'href' => $canCrm ? './?view=sales-crm&section=leads' : '', 'available' => $canCrm, 'availability' => $canCrm ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY'],
            ['key' => 'master-prospects', 'label' => 'Prospects', 'href' => $canCrm ? './?view=sales-crm&section=prospects' : '', 'available' => $canCrm, 'availability' => $canCrm ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY'],
            ['key' => 'master-appointments', 'label' => 'Appointments', 'href' => $canCrm ? './?view=sales-crm&section=appointments' : '', 'available' => $canCrm, 'availability' => $canCrm ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY'],
            ['key' => 'master-campaigns', 'label' => 'Campaigns', 'href' => $canCrm ? './?view=sales-crm&section=campaigns' : '', 'available' => $canCrm, 'availability' => $canCrm ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY'],
            ['key' => 'master-opportunities', 'label' => 'Opportunities', 'href' => '', 'available' => false, 'availability' => 'UNAVAILABLE_DEPENDENCY'],
        ]],
        ['group' => 'Selling Masters', 'items' => [
            ['key' => 'master-customers', 'label' => 'Customers', 'href' => '', 'available' => false, 'availability' => 'NOT_IMPLEMENTED'],
            ['key' => 'master-quotations', 'label' => 'Quotations', 'href' => '', 'available' => false, 'availability' => 'UNAVAILABLE_DEPENDENCY'],
            ['key' => 'master-orders', 'label' => 'Sales orders', 'href' => '', 'available' => false, 'availability' => 'UNAVAILABLE_DEPENDENCY'],
        ]],
        ['group' => 'Reports', 'items' => [
            ['key' => 'report-campaign-efficiency', 'label' => 'Campaign efficiency', 'href' => $canCrm ? './?view=sales-crm&section=campaigns' : '', 'available' => $canCrm, 'availability' => $canCrm ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY'],
            ['key' => 'report-sales-analytics', 'label' => 'Sales analytics', 'href' => '', 'available' => false, 'availability' => 'NOT_IMPLEMENTED'],
            ['key' => 'report-territory-performance', 'label' => 'Territory performance', 'href' => '', 'available' => false, 'availability' => 'NOT_IMPLEMENTED'],
        ]],
    ];

    $dependencies = [
        ['contract' => 'sales.lead.summary.v1', 'status' => $canCrm ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY'],
        ['contract' => 'sales.campaign.summary.v1', 'status' => $canCrm ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY'],
        ['contract' => 'sales.opportunity.summary.v1', 'status' => 'UNAVAILABLE_DEPENDENCY'],
        ['contract' => 'sales.quotation.summary.v1', 'status' => 'UNAVAILABLE_DEPENDENCY'],
        ['contract' => 'sales.order.summary.v1', 'status' => 'UNAVAILABLE_DEPENDENCY'],
    ];
    $alerts[] = ['key' => 'downstream-documents', 'severity' => 'INFO', 'label' => 'Opportunity, Quotation, and Sales Order summaries await their registered Sales services.', 'href' => ''];

    return [
        'summary' => $summary,
        'queue' => $queue,
        'activity' => $activity,
        'setup' => $setup,
        'alerts' => array_slice($alerts, 0, 6),
        'shortcuts' => $shortcuts,
        'directories' => $directories,
        'dependencies' => $dependencies,
    ];
}
