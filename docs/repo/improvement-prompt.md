I need you to audit and improve the organization of this repository.

This is a Markdown content library, not an application codebase. It mainly contains reusable prompts, interview-preparation notes, templates, and project-specific documentation.

Your job is to review the current repository structure, identify organization problems, propose a better structure, and then implement the approved changes safely.

## Objectives

1. Analyze the current directory and file structure.
2. Identify problems such as:
   - unclear categorization
   - inconsistent folder depth
   - duplicate or overlapping content
   - weak naming consistency
   - misplaced files
   - missing index or documentation files
   - root-level clutter
3. Propose a clearer information architecture for a prompt library.
4. Create a migration plan with exact file moves and renames.
5. After approval, implement the changes and update any affected documentation.

## Repository Context

- Repository type: curated prompt and interview-prep knowledge base
- Primary file type: Markdown
- Typical content groups:
  - reusable prompts
  - interview preparation materials
  - company-specific content
  - templates/messages
  - supporting documentation

## What I Want You To Do

### Phase 1: Analyze

1. List all files and folders in the repository.
2. Group the current content into logical categories.
3. Point out structural issues, including:
   - directories that mix unrelated content
   - places where naming is inconsistent
   - places where folders are too broad or too specific
   - content that should probably be merged, split, or relocated
   - missing README or index files in important sections

### Phase 2: Propose

Propose a new structure that is appropriate for a content repository like this one.

Prefer organization by content type and audience, for example:
- `prompts/`
- `interview-prep/`
- `templates/`
- `docs/`
- `company/` only if cross-cutting company content truly needs a top-level home

Your proposal should define:
- the target folder structure
- naming conventions
- when to use nested folders
- where company-specific files should live
- where general-purpose documentation should live
- whether index files should exist in each major section

### Phase 3: Migration Plan

Provide a precise migration plan before making changes.

For every affected file, show:
- current path
- proposed path
- whether it is being moved, renamed, merged, or left unchanged

Also list:
- new directories to create
- README or index files to add
- any files that appear redundant or obsolete

### Phase 4: Approval Gate

Do not move, rename, delete, or rewrite files until you first:

1. Show the proposed new structure
2. Show the migration table
3. Explain the reasoning briefly
4. Ask for confirmation

### Phase 5: Implementation After Approval

Once approved, implement the changes by:
- creating directories
- moving and renaming files
- updating internal references in Markdown files if paths change
- updating root documentation such as `README.md` and `PROMPTS_INDEX.md`
- adding section-level README files if they improve navigation

## Constraints

- Preserve all existing content unless there is a strong reason to merge duplicates.
- Prefer minimal-disruption changes over unnecessary reshuffling.
- Keep naming in lowercase kebab-case unless an existing convention clearly requires otherwise.
- Do not invent application-layer concepts like controllers, services, components, or models unless the repository actually contains source code.
- Treat this as an information-architecture and maintainability task, not a software refactor.

## Output Format

Respond in this order:

1. Current structure summary
2. Identified issues
3. Proposed target structure
4. Migration table
5. Risks or assumptions
6. Confirmation request before execution
