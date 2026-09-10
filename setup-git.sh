#!/bin/bash
echo "🔧 GitHub Setup for HDHome"
echo "=========================="
echo ""
echo "Step 1: GitHub Personal Access Token banao"
echo "  → https://github.com/settings/tokens/new"
echo "  → Name: HDHome"
echo "  → Expiration: 90 days (or No expiration)"
echo "  → Scopes: repo (full control)"
echo "  → Click 'Generate token'"
echo "  → Copy the token (ghp_xxxxx...)"
echo ""
echo "Step 2: Token paste karo"
read -p "GitHub Token: " TOKEN
if [ -z "$TOKEN" ]; then
    echo "❌ Token empty hai!"
    exit 1
fi
echo "https://$TOKEN@github.com" > ~/.git-credentials
git config --global credential.helper store
git config --global user.name "HDHome"
git config --global user.email "hdhome@users.noreply.github.com"
echo "✅ GitHub credentials saved!"
echo ""
echo "Step 3: Push karo"
cd /root/Hdhome
git push origin master
echo ""
echo "🚀 Deploy ho gaya!"
