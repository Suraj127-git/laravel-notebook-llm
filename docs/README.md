# NotebookLLM — Documentation Index

This directory contains technical documentation for the NotebookLLM project. Start with the root [README.md](../README.md) for the project overview, quick start, and API reference.

---

## Structure

```
docs/
├── README.md                        ← This file — docs navigation
├── backend/
│   ├── overview.md                  ← Architecture, tech stack, key design decisions
│   ├── structure.md                 ← Directory layout and file-by-file guide
│   └── code-explanation.md          ← Deep-dives into core components
├── frontend/
│   ├── overview.md                  ← Component architecture, state management
│   ├── structure.md                 ← Directory layout and file guide
│   └── code-explanation.md          ← Key component and hook explanations
└── futureImpl/
    ├── enhancement-plan.md          ← Roadmap (tracks what's shipped vs planned)
    └── nightwatch-logging-plan.md   ← Observability implementation (shipped)
```

---

## Quick Navigation

| Topic | File |
|---|---|
| Docker setup & services | [../README.md](../README.md#quick-start-docker) |
| API endpoints | [../README.md](../README.md#api-reference) |
| RAG pipeline | [../README.md](../README.md#rag-flow) |
| Backend architecture | [backend/overview.md](backend/overview.md) |
| Backend file guide | [backend/structure.md](backend/structure.md) |
| KnowledgeAgent internals | [backend/code-explanation.md](backend/code-explanation.md) |
| Frontend architecture | [frontend/overview.md](frontend/overview.md) |
| Frontend file guide | [frontend/structure.md](frontend/structure.md) |
| Redux + RTK Query | [frontend/code-explanation.md](frontend/code-explanation.md) |
| Nightwatch logging | [futureImpl/nightwatch-logging-plan.md](futureImpl/nightwatch-logging-plan.md) |
| Feature roadmap | [futureImpl/enhancement-plan.md](futureImpl/enhancement-plan.md) |
