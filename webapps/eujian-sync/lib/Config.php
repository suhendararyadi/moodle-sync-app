<?php
/**
 * Config.php - Baca/tulis config.json untuk E-UJIAN Sync Web App
 * Format config.json sama persis dengan CLI .NET
 */
class Config {
    private string $path;
    private array $data = [];

    public function __construct(?string $configPath = null) {
        $this->path = $configPath ?? __DIR__ . '/../config.json';
        $this->load();
    }

    private function load(): void {
        if (file_exists($this->path)) {
            $raw = file_get_contents($this->path);
            $this->data = json_decode($raw, true) ?? [];
        } else {
            // Default config
            $this->data = [
                'MasterUrl'   => 'https://lms.smknegeri9garut.sch.id/',
                'MasterToken' => '',
                'LocalUrl'    => 'http://localhost:8080/',
                'LocalToken'  => '',
                'RoomId'      => '1',
                'RoomName'    => 'Server Kelas',
            ];
            $this->save();
        }
    }

    public function save(): bool {
        return (bool) file_put_contents(
            $this->path,
            json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public function get(string $key, mixed $default = null): mixed {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void {
        $this->data[$key] = $value;
    }

    public function all(): array {
        return $this->data;
    }

    // Shortcut getters
    public function masterUrl(): string   { return rtrim($this->get('MasterUrl', ''), '/') . '/'; }
    public function masterToken(): string { return $this->get('MasterToken', ''); }
    public function localUrl(): string    { return rtrim($this->get('LocalUrl', ''), '/') . '/'; }
    public function localToken(): string  { return $this->get('LocalToken', ''); }
    public function roomId(): string      { return $this->get('RoomId', '1'); }
    public function roomName(): string    { return $this->get('RoomName', 'Server Kelas'); }
}
