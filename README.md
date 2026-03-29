# Awesome Prompts

A curated Markdown library of reusable prompts, interview preparation materials, templates, and supporting documentation.

## Repository Structure

```text
awesome-prompts/
|-- README.md
|-- PROMPTS_INDEX.md
|-- docs/
|   |-- README.md
|   |-- repo/
|   `-- company/
|-- prompts/
|   |-- README.md
|   |-- general/
|   |-- laravel/
|   |-- node/
|   |-- vue/
|   |-- exams/
|   `-- company/
|-- interview-prep/
|   |-- README.md
|   |-- general/
|   |-- laravel/
|   `-- company/
`-- templates/
    |-- README.md
    `-- messages/
```

## Sections

- `prompts/` contains reusable prompts grouped by domain, audience, or company.
- `interview-prep/` contains question banks, prep guides, and interview notes.
- `templates/` contains reusable content templates such as messages.
- `docs/` contains supporting documentation, planning artifacts, and project-specific reference material.

## Organization Rules

- Keep folders and filenames in lowercase kebab-case where possible.
- Put general-purpose prompts under `prompts/<topic>/<purpose>/`.
- Put company-specific prompts under `prompts/company/<company>/`.
- Put interview preparation material under `interview-prep/<general|technology|company>/`.
- Put long-form documentation and plans under `docs/` instead of `prompts/`.
- Add or update section-level `README.md` files when a folder grows.

## Usage

- Browse `PROMPTS_INDEX.md` for a quick inventory.
- Start in the section `README.md` files if you want to navigate by content type.
- Keep new additions close to the existing structure rather than creating one-off top-level folders.
