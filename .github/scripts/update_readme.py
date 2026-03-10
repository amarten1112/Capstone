import os
import re

# Read counts from environment variables
todo = os.environ.get('TODO', '0')
in_progress = os.environ.get('IN_PROGRESS', '0')
done = os.environ.get('DONE', '0')

# Build replacement block
status_block = """<!-- PROJECT-STATUS:START -->
## Project Board Status

| Status | Count |
|--------|-------|
| Todo | """ + todo + """ |
| In Progress | """ + in_progress + """ |
| Done | """ + done + """ |

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
