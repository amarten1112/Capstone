import os
import re

# Read overall counts from environment variables
todo = os.environ.get('TODO', '0')
in_progress = os.environ.get('IN_PROGRESS', '0')
done = os.environ.get('DONE', '0')

# Read my items counts from environment variables
my_todo = os.environ.get('MY_TODO', '0')
my_in_progress = os.environ.get('MY_IN_PROGRESS', '0')
my_done = os.environ.get('MY_DONE', '0')
my_total = int(my_todo) + int(my_in_progress) + int(my_done)

# Build replacement block
status_block = """<!-- PROJECT-STATUS:START -->
## Project Board Status

### Overall Board

| Status | Count |
|--------|-------|
| Todo | """ + todo + """ |
| In Progress | """ + in_progress + """ |
| Done | """ + done + """ |

### My Items (assigned to @amarten1112)

| Status | Count |
|--------|-------|
| Todo | """ + my_todo + """ |
| In Progress | """ + my_in_progress + """ |
| Done | """ + my_done + """ |
| **Total** | **""" + str(my_total) + """** |

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

print(f"Updated README:")
print(f"  Overall: Todo={todo}, In Progress={in_progress}, Done={done}")
print(f"  My Items: Todo={my_todo}, In Progress={my_in_progress}, Done={my_done}")
