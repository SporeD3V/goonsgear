#!/usr/bin/env python3
"""Step 1: Verify WP variations with images in legacy DB"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD'
BASE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

def run_cmd(client, cmd, label=None):
    if label:
        print(f"\n{'='*70}\n{label}\n{'='*70}")
    stdin, stdout, stderr = client.exec_command(cmd, timeout=90)
    out = stdout.read().decode().strip()
    err = stderr.read().decode().strip()
    if out:
        print(out)
    if err and 'deprecated' not in err.lower():
        print(f"STDERR: {err}")
    return out

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        print(f"✓ Connected\n")
        
        # Count total WP variations
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e "'
                'SELECT COUNT(*) as total_variations '
                'FROM wp_posts '
                'WHERE post_type = \\"product_variation\\" '
                'AND post_status = \\"publish\\";'
                '"',
                'Step 1a: Total WP Product Variations')
        
        # Count variations with _thumbnail_id
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e "'
                'SELECT COUNT(DISTINCT pm.post_id) as variations_with_images '
                'FROM wp_postmeta pm '
                'JOIN wp_posts p ON p.ID = pm.post_id '
                'WHERE pm.meta_key = \\"_thumbnail_id\\" '
                'AND p.post_type = \\"product_variation\\" '
                'AND p.post_status = \\"publish\\" '
                'AND pm.meta_value != \\"\\";'
                '"',
                'Step 1b: WP Variations with _thumbnail_id')
        
        # Sample variations with images
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e "'
                'SELECT '
                '  p.ID as variation_id, '
                '  p.post_parent as product_id, '
                '  pm.meta_value as attachment_id '
                'FROM wp_postmeta pm '
                'JOIN wp_posts p ON p.ID = pm.post_id '
                'WHERE pm.meta_key = \\"_thumbnail_id\\" '
                'AND p.post_type = \\"product_variation\\" '
                'AND p.post_status = \\"publish\\" '
                'AND pm.meta_value != \\"\\" '
                'LIMIT 10;'
                '"',
                'Step 1c: Sample WP Variations with Images (First 10)')
        
        # Get variation SKUs for reference
        run_cmd(client,
                f'cd {BASE_PATH} && mysql -u LEGACYgoonsgearUSER -pWSvlby6AftxXYxpWFddL LEGACYgoonsgearDB -e "'
                'SELECT '
                '  p.ID as variation_id, '
                '  pm_sku.meta_value as sku, '
                '  pm_thumb.meta_value as thumbnail_id '
                'FROM wp_posts p '
                'LEFT JOIN wp_postmeta pm_sku ON pm_sku.post_id = p.ID AND pm_sku.meta_key = \\"_sku\\" '
                'LEFT JOIN wp_postmeta pm_thumb ON pm_thumb.post_id = p.ID AND pm_thumb.meta_key = \\"_thumbnail_id\\" '
                'WHERE p.post_type = \\"product_variation\\" '
                'AND p.post_status = \\"publish\\" '
                'AND pm_thumb.meta_value IS NOT NULL '
                'AND pm_thumb.meta_value != \\"\\" '
                'LIMIT 10;'
                '"',
                'Step 1d: Sample Variation Details (ID, SKU, Thumbnail)')
        
        print("\n" + "="*70)
        print("✓ Step 1 completed - Legacy DB variant image verification")
        print("="*70)
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
