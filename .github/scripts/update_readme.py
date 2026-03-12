import os
import re
import json

# Read overall counts from environment variables
todo = os.environ.get('TODO', '0')
in_progress = os.environ.get('IN_PROGRESS', '0')
done = os.environ.get('DONE', '0')

# Read project data JSON to build phases table
phases_rows = []
try:
    with open('project_data.json', 'r', encoding='utf-8') as f:
        data = json.load(f)

    items = data['data']['user']['projectV2']['items']['nodes']

    # Filter for Phase-level issues only (title starts with "Phase ")
    phase_items = []
    for item in items:
        content = item.get('content') or {}
        title = content.get('title', '')
        if title.lower().startswith('phase '):
            phase_items.append(item)

    # Sort by issue number to get Phase 1, 2, 3... order
    phase_items.sort(key=lambda x: (x.get('content') or {}).get('number', 999))

    for item in phase_items:
        content = item.get('content') or {}
        title = content.get('title', '')
        status = (item.get('fieldValueByName') or {}).get('name', 'Unknown')
        summary = content.get('subIssuesSummary') or {}
        total = summary.get('total', 0)
        completed = summary.get('completed', 0)

        # Build progress bar: filled squares out of 10
        if total > 0:
            filled = round(completed / total * 10)
            bar = '█' * filled + '░' * (10 - filled)
            progress = f'{bar} {completed}/{total}'
        else:
            progress = 'N/A'

        # Shorten title: remove "Phase N: " prefix to just "Phase N - Short Name"
        phases_rows.append((title, status, progress))

except Exception as e:
    print(f'Warning: could not parse phase data: {e}')

# Build phases table rows
if phases_rows:
    phase_table_rows = '\n'.join(
        f'| {title} | {status} | {progress} |'
        for title, status, progress in phases_rows
    )
    phases_section = f"""### Phase Progress

| Phase | Status | Sub-tasks |
|-------|--------|-----------|
{phase_table_rows}"""
else:
    phases_section = ''

# Build replacement block
status_block = f"""<!-- PROJECT-STATUS:START -->
## Project Board Status

### Overall Board

| Status | Count |
|--------|-------|
| Todo | {todo} |
| In Progress | {in_progress} |
| Done | {done} |

{phases_section}

> *Last updated automatically by GitHub Actions*
<!-- PROJECT-STATUS:END -->"""

# Read README
with open('README.md', 'r', encoding='utf-8') as f:
    content = f.read()

# Replace between markers
pattern = r'<!-- PROJECT-STATUS:START -->.*?<!-- PROJECT-STATUS:END -->'
new_content = re.sub(pattern, status_block, content, flags=re.DOTALL)

# Write README
with open('README.md', 'w', encoding='utf-8') as f:
    f.write(new_content)

print(f"Updated README: Todo={todo}, In Progress={in_progress}, Done={done}")
print(f"Phases found: {len(phases_rows)}")
