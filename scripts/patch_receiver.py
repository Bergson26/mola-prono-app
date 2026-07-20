"""
Patch SharePlugin.java to fix Android 12+ crash:
  SecurityException: RECEIVER_EXPORTED or RECEIVER_NOT_EXPORTED must be specified
  Source: @capacitor/share - SharePlugin.java load() method
"""
import glob, sys

OLD = 'getActivity().registerReceiver(broadcastReceiver, new IntentFilter(Intent.EXTRA_CHOSEN_COMPONENT));'
NEW = (
    'if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.S) {\n'
    '            getActivity().registerReceiver(broadcastReceiver,\n'
    '                new IntentFilter(Intent.EXTRA_CHOSEN_COMPONENT),\n'
    '                android.content.Context.RECEIVER_NOT_EXPORTED);\n'
    '        } else {\n'
    '            getActivity().registerReceiver(broadcastReceiver, new IntentFilter(Intent.EXTRA_CHOSEN_COMPONENT));\n'
    '        }'
)

files = glob.glob(
    'node_modules/@capacitor/share/android/**/*.java',
    recursive=True
)

patched = 0
for path in files:
    content = open(path, encoding='utf-8').read()
    if OLD in content:
        open(path, 'w', encoding='utf-8').write(content.replace(OLD, NEW))
        print(f'Patched: {path}')
        patched += 1

if patched == 0:
    print('ERROR: registerReceiver line not found in @capacitor/share - check plugin version')
    sys.exit(1)
else:
    print(f'Done: {patched} file(s) patched')
