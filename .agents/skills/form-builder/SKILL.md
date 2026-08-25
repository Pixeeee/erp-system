---
name: form-builder
description: Build interactive document forms, questionnaires, and BuilderX ERP form-builder layouts with clear modal panels and field creation or editing flows.
license: MIT
metadata:
  version: "1.0"
  author: claude-office-skills
  category: template
  tags:
    - form
    - builder
    - interactive
    - docassemble
  department: All
  capabilities:
    - form_creation
    - interactive_documents
  languages:
    - en
    - zh
---

# Form Builder Skill

## Overview

This skill enables creation of intelligent document forms using **docassemble** - a platform for guided interviews that generate documents. Create questionnaires that adapt based on answers.

## BuilderX ERP Form Builder Layout

When applying this skill to BuilderX ERP form-builder modals:

- Use the modal header as the only place for the form-builder title and description.
- Do not repeat the same title, description, field count, or setup summary at the top of the modal body.
- Start all body panels on the same top row immediately below the modal header.
- Do not use page-level sticky offsets inside modal panels; modal builder panels should align at `top: 0` within the scroll body.
- Keep panel roles task-specific: field types or sections on the left, form canvas or current fields in the center, and field setup or field editing on the right.
- Add subtle vertical divider lines between adjacent modal panels so admins can quickly identify each workspace area.
- Place counts inside compact panel labels, section chips, or the modal footer only when they help the task.

## BuilderX Odoo-Style Form Builder Layout

For ERP admins and non-technical users, prefer an Odoo-like form editor shape:

- Name the three primary work areas plainly: `Field Toolbox`, `Form Layout`, and `Field Properties`.
- Use visible panel dividers and section divider bars so the workspace reads as three separate jobs: choose, arrange, configure.
- In the form canvas, render each section as a labeled band with a compact field count.
- Render fields as bordered form rows, not loose text. Each row should show the field label, field key, field type, required/visible/core badges, and a simple preview control.
- Add a clear selected-field state with an accent border or soft glow so the admin knows which row the properties panel is editing.
- Group properties for human scanning: `1. Field identity`, `2. Display and placement`, `3. Options`, and `4. Validation`.
- Keep technical identifiers visible, but label them as field keys or system names instead of presenting raw terms without context.
- Preserve all existing builder functions: field creation, current-field editing, drag/drop section changes, type selection, required/visible flags, placeholders, help text, options, confirmation, and persisted saves.

## HR Dashboard Google Forms-Style Builder

When the user asks for a Form Builder that works like Google Forms inside the HR Department:

- Keep the HR Dashboard as a broad module workspace for setup, shortcuts, records, reports, and tools. Form Builder is one dashboard feature, not the dashboard's entire content.
- Treat HR Dashboard as the only entry point for custom HR form creation and editing. Do not place `Form Builder`, `Edit Form`, or custom-form creation controls inside Employee Profile, Department, Job Position, or other feature record pages.
- Put the HR feature navigation inside the Form Builder header as clear buttons or tabs, alongside `New Form` and `Existing Form` controls.
- `New Form` starts a blank canvas for the selected HR feature. `Existing Form` shows saved forms for the selected feature; clicking one loads it into the builder for editing.
- Include built-in ERP form sections in `Existing Forms`, not only admin-created forms. For Employee Profiles, identify Overview, Joining, Address & Contacts, Attendance & Leaves, Salary, Personal, Profile, and Exit as separate built-in forms with live field counts.
- Visually separate `Built-in Forms` from `Custom Forms`. Opening a built-in form launches the current-field editor for that live ERP section; opening a custom form loads its saved question schema.
- Treat Form Builder as a dashboard-level tool for creating separate custom forms. It should not preload the built-in ERP master form unless the user explicitly imports fields.
- Start new forms from a blank canvas with an editable title, description, target HR feature, status, and empty question list.
- Shape `New Form` like a familiar Google Forms/Odoo workbench: a compact question toolbox, a central blank form canvas, and a clear form settings/action area. Adding a field type should immediately create a question card on the canvas.
- Show existing forms for the selected HR feature. Clicking an existing form opens that form in the builder for editing.
- Provide question cards with controls for label/question text, help text, field type, dropdown/checkbox options, required toggle, duplicate, delete, and move up/down.
- Include a Preview mode so admins can see the respondent-facing form without leaving the builder.
- Persist form metadata and the question layout as company-scoped records. Keep create/update transactional, audited, and directly read back after save.
- Keep this separate from `Customize Form`: Customize Form changes the built-in ERP form fields, while dashboard Form Builder creates admin-defined forms attached to HR features.

## How to Use

1. Describe the form or document you need
2. Specify conditional logic requirements
3. I'll create docassemble interview YAML

**Example prompts:**
- "Create an intake form for new clients"
- "Build a conditional questionnaire for legal documents"
- "Generate a multi-step form for contract generation"
- "Design an interactive document assembly form"

## Domain Knowledge

### Interview Structure

```yaml
metadata:
  title: Client Intake Form
  short title: Intake

---
question: |
  What is your name?
fields:
  - First Name: first_name
  - Last Name: last_name

---
question: |
  What type of service do you need?
field: service_type
choices:
  - Contract Review
  - Document Drafting
  - Consultation

---
mandatory: True
question: |
  Thank you, ${ first_name }!
subquestion: |
  We will contact you about your ${ service_type } request.
```

### Conditional Logic

```yaml
---
question: |
  Are you a business or individual?
field: client_type
choices:
  - Business
  - Individual

---
if: client_type == "Business"
question: |
  What is your company name?
fields:
  - Company: company_name
  - EIN: ein
    required: False

---
if: client_type == "Individual"
question: |
  What is your date of birth?
fields:
  - Birthdate: birthdate
    datatype: date
```

### Field Types

```yaml
fields:
  # Text
  - Name: name
  
  # Email
  - Email: email
    datatype: email
  
  # Number
  - Age: age
    datatype: integer
  
  # Currency
  - Amount: amount
    datatype: currency
  
  # Date
  - Start Date: start_date
    datatype: date
  
  # Yes/No
  - Agree to terms?: agrees
    datatype: yesno
  
  # Multiple choice
  - Color: color
    choices:
      - Red
      - Blue
      - Green
  
  # Checkboxes
  - Select options: options
    datatype: checkboxes
    choices:
      - Option A
      - Option B
  
  # File upload
  - Upload document: document
    datatype: file
```

### Document Generation

```yaml
---
mandatory: True
question: |
  Your document is ready.
attachment:
  name: Contract
  filename: contract
  content: |
    # Service Agreement
    
    This agreement is between **${ client_name }**
    and **Service Provider**.
    
    ## Services
    ${ service_description }
    
    ## Payment
    Total amount: ${ currency(amount) }
    
    Date: ${ today() }
```

## Example: Client Intake

```yaml
metadata:
  title: Legal Client Intake
  short title: Intake

---
objects:
  - client: Individual

---
question: |
  Welcome to our intake form.
subquestion: |
  Please answer the following questions.
continue button field: intro_screen

---
question: |
  What is your name?
fields:
  - First Name: client.name.first
  - Last Name: client.name.last
  - Email: client.email
    datatype: email
  - Phone: client.phone
    required: False

---
question: |
  What type of matter is this?
field: matter_type
choices:
  - Contract: contract
  - Dispute: dispute
  - Advisory: advisory

---
if: matter_type == "contract"
question: |
  Contract Details
fields:
  - Contract Type: contract_type
    choices:
      - Employment
      - Service Agreement
      - NDA
  - Other Party: other_party
  - Estimated Value: contract_value
    datatype: currency

---
mandatory: True
question: |
  Thank you, ${ client.name.first }!
subquestion: |
  **Summary:**
  
  - Name: ${ client.name }
  - Email: ${ client.email }
  - Matter: ${ matter_type }
  
  We will contact you within 24 hours.
```

## Resources

- [docassemble Documentation](https://docassemble.org/docs.html)
- [GitHub Repository](https://github.com/jhpyle/docassemble)
