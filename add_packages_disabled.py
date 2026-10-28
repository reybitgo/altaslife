with open(r'C:\laragon\www\altaslife\install.sql', 'r', encoding='utf-8', errors='ignore') as f:
    content = f.read()

old = "    ('shop_max_delivery_attempts',  '3');"
new = "    ('shop_max_delivery_attempts',  '3'),\n    ('packages_disabled',      '0');"

if old in content and "'packages_disabled'" not in content:
    content = content.replace(old, new)
    with open(r'C:\laragon\www\altaslife\install.sql', 'w', encoding='utf-8') as f:
        f.write(content)
    print('done')
else:
    print('skip')
