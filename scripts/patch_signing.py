#!/usr/bin/env python3
import re

with open('android/app/build.gradle', 'r') as f:
    src = f.read()

PROPS_READER = (
    "\ndef keystoreProps = new Properties()\n"
    "def propsFile = rootProject.file('keystore.properties')\n"
    "if (propsFile.exists()) { propsFile.withInputStream { keystoreProps.load(it) } }\n"
)

SIGNING_CONFIGS = (
    "    signingConfigs {\n"
    "        release {\n"
    "            storeFile     file(keystoreProps.getProperty('storeFile', ''))\n"
    "            storePassword keystoreProps.getProperty('storePassword', '')\n"
    "            keyAlias      keystoreProps.getProperty('keyAlias', '')\n"
    "            keyPassword   keystoreProps.getProperty('keyPassword', '')\n"
    "        }\n"
    "    }\n"
)

src = src.replace('android {', PROPS_READER + 'android {', 1)
src = src.replace('    buildTypes {', SIGNING_CONFIGS + '    buildTypes {', 1)
src = re.sub(
    r'(buildTypes\s*\{[^{]*?release\s*\{)',
    r'\1\n            signingConfig signingConfigs.release',
    src, count=1, flags=re.DOTALL
)

with open('android/app/build.gradle', 'w') as f:
    f.write(src)

print("Signing config injecte avec succes")
