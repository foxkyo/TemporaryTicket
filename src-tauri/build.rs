fn main() {
    tauri_build::try_build(
        tauri_build::Attributes::new().app_manifest(
            tauri_build::AppManifest::new().commands(&["silent_print"]),
        ),
    )
    .expect("failed to run tauri-build");
}
