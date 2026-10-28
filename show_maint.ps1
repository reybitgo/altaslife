$lines = Get-Content 'C:\laragon\www\altaslife\views\admin\settings.php'
$lines[112..158] | Out-File 'C:\laragon\www\altaslife\maint_section2.txt'
