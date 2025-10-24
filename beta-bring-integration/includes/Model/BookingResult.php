<?php
namespace BeTA\Bring\Model;

class BookingResult {
    public string $consignment_no;
    public ?string $label_url;
    public ?string $tracking_url;
    public string $service_id;
    public string $booked_at;
    public array $raw;

    public function __construct( string $consignment_no, ?string $label_url, ?string $tracking_url, string $service_id, string $booked_at, array $raw = [] ) {
        $this->consignment_no = $consignment_no;
        $this->label_url = $label_url;
        $this->tracking_url = $tracking_url;
        $this->service_id = $service_id;
        $this->booked_at = $booked_at;
        $this->raw = $raw;
    }

    public function to_array(): array {
        return [
            'consignment_no' => $this->consignment_no,
            'label_url' => $this->label_url,
            'tracking_url' => $this->tracking_url,
            'service_id' => $this->service_id,
            'booked_at' => $this->booked_at,
            'raw' => $this->raw,
        ];
    }
}
