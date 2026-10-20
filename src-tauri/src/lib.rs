#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
  tauri::Builder::default()
    .setup(|app| {
      if cfg!(debug_assertions) {
        app.handle().plugin(
          tauri_plugin_log::Builder::default()
            .level(log::LevelFilter::Info)
            .build(),
        )?;
      }
      Ok(())
    })
    .invoke_handler(tauri::generate_handler![print_tickets])
    .run(tauri::generate_context!())
    .expect("error while building tauri application");
}

#[tauri::command]
fn print_tickets(pages: Vec<serde_json::Value>) -> Result<(), String> {
    // 將 pages 轉換為 JSON 字串
    let pages_json = serde_json::to_string(&pages).map_err(|e| e.to_string())?;

    // 呼叫 Python 腳本進行列印
    let output = std::process::Command::new("python3")
        .arg("print_tickets.py")
        .arg(&pages_json)
        .output()
        .map_err(|e| format!("無法執行 Python: {}", e))?;

    if !output.status.success() {
        let stderr = String::from_utf8_lossy(&output.stderr);
        return Err(format!("列印失敗: {}", stderr));
    }

    Ok(())
}
