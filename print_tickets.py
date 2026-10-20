#!/usr/bin/env python3
import sys
import json
import subprocess

def print_tickets(pages_data):
    """
    透過 Python 直接送印表機列印車票
    pages 已經按照分頁規則處理：
    1. 不同使用者強迫分頁
    2. 同1使用者每5張分1頁
    """
    try:
        pages = json.loads(pages_data)

        print(f"列印 {len(pages)} 頁車票")

        for page in pages:
            print(f"頁碼: {page['pageNumber']}/{page['totalPages']}")
            print(f"搭乘者: {page['studentDisplay']}")
            print(f"總票價: {page['totalPrice']}")
            print(f"條碼: {page['barcode']}")
            print(f"車票數量: {len(page['items'])}")
            print("---")

            # 這裡可以根據您的印表機型號和需求調整
            # 例如使用 ESC/POS 指令或 cups 命令

            # 範例：使用 cups 命令列印
            # printer_name = "您的印表機名稱"
            # subprocess.run(['lp', '-d', printer_name, 'ticket.pdf'])

            # 或者使用 Python 的打印庫
            # import cups
            # conn = cups.Connection()
            # conn.printFile(printer_name, 'ticket.pdf', 'Ticket Job', {})

        return True
    except Exception as e:
        print(f"錯誤: {e}", file=sys.stderr)
        return False

if __name__ == "__main__":
    if len(sys.argv) > 1:
        pages_json = sys.argv[1]
        success = print_tickets(pages_json)
        sys.exit(0 if success else 1)
    else:
        print("請提供車票頁面 JSON 數據", file=sys.stderr)
        sys.exit(1)
