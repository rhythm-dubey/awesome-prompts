PROMPTS INDEX

Structure: prompts/<technology|general>/<purpose>/ and interview-prep/<general|technology|company>/ — path carries technology/company; filenames do not repeat it (kebab-case).

Root files:
- README.md
- PROMPTS_INDEX.md

templates/
- messages/whatsapp-message.md — WhatsApp message template

prompts/
- general/interview/screening-round.md — Screening interview prompt (general)
- general/interview/second-round.md — Second-round interview prompt (general)
- general/interview/rate-limiter.md — Rate limiter prompt (general)
- node/learning/learn.md — Node.js learning prompt
- laravel/tools/qna-generator.md — Laravel Q&A generator tool
- vue/interview/interview.md — Vue.js interview prompt
- company/dotsquare/interview.md — DotSquare company-specific prompt

interview-prep/
- general/hr-interview.md — HR interview questions & answers
- laravel/interview.md — Core Laravel interview Q&A
- laravel/screening-qna.md — Laravel screening-level Q&A
- laravel/core-concepts-qna.md — Laravel core concepts Q&A
- laravel/senior-qna.md — Laravel senior (3–4 years) Q&A
- company/square/preparation-guide.md — Square Laravel preparation guide
- company/square/guide-summary.md — Square Laravel guide summary
- company/square/guide-round2.md … guide-round8.md — Square Laravel round guides
- company/dotsquare/interview-formatted.md — DotSquare Laravel interview Q&A (formatted)

Notes:
- Content without a specific technology lives under general/.
- Company-specific content lives under company/<name> in both prompts/ and interview-prep/.
- Filenames are kebab-case and do not repeat the folder name (technology or company).
