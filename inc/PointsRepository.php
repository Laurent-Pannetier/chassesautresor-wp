<?php

declare(strict_types=1);

/**
 * Handle points storage in a dedicated table instead of user meta.
 */
class PointsRepository
{
    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Fully qualified table name.
     *
     * @var string
     */
    private string $table;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'user_points';
    }

    /**
     * Return current balance for a user.
     */
    public function getBalance(int $userId): int
    {
        $sql = $this->wpdb->prepare(
            "SELECT balance FROM {$this->table} WHERE user_id = %d ORDER BY id DESC LIMIT 1",
            $userId
        );

        $balance = $this->wpdb->get_var($sql);

        return $balance !== null ? (int) $balance : 0;
    }

    /**
     * Record a points operation and return the new balance.
     */
    public function addPoints(
        int $userId,
        int $delta,
        string $reason = '',
        string $originType = 'admin',
        ?int $originId = null
    ): int
    {
        $current = $this->getBalance($userId);
        $newBalance = max(0, $current + $delta);

        $data = [
            'user_id'     => $userId,
            'balance'     => $newBalance,
            'points'      => $delta,
            'reason'      => $reason,
            'origin_type' => $originType,
        ];
        $format = ['%d', '%d', '%d', '%s', '%s'];

        if ($originId !== null) {
            $data['origin_id'] = $originId;
            $format[] = '%d';
        }

        $this->wpdb->insert(
            $this->table,
            $data,
            $format
        );

        return $newBalance;
    }

    /**
     * Retrieve points operations for a user ordered by newest first.
     *
     * @param int $userId User identifier.
     * @param int $limit  Maximum number of rows to return.
     * @param int $offset Offset for pagination.
     * @return array[] List of operations.
     */
    public function getHistory(int $userId, int $limit, int $offset = 0): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT id, request_date, origin_type, origin_id, reason, points, balance FROM {$this->table} WHERE user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d",
            $userId,
            $limit,
            $offset
        );

        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Count conversion requests for a user.
     *
     * @param int      $userId User identifier.
     * @param string|null $status Optional request status filter.
     *
     * @return int Number of requests.
     */
    public function countConversionRequests(?int $userId = null, ?string $status = null): int
    {
        $where  = "origin_type = 'conversion'";
        $params = [];

        if ($userId !== null) {
            $where   .= ' AND user_id = %d';
            $params[] = $userId;
        }

        if ($status !== null) {
            $where   .= ' AND request_status = %s';
            $params[] = $status;
        }

        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where}";
        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Count total number of operations for a user.
     */
    public function countHistory(int $userId): int
    {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d",
            $userId
        );

        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Record a conversion request with pending status and return the inserted row ID.
     *
     * @param int   $userId    User identifier.
     * @param int   $points    Point variation (negative for conversions).
     * @param float $amountEur Equivalent amount in euros.
     */
    public function logConversionRequest(int $userId, int $points, float $amountEur): int
    {
        $current = $this->getBalance($userId);
        $newBalance = max(0, $current + $points);
        $amountEur = round($amountEur, 2);

        $reason = sprintf(__('Demande de conversion de %d points', 'chassesautresor'), abs($points));

        $this->wpdb->insert(
            $this->table,
            [
                'user_id'        => $userId,
                'balance'        => $newBalance,
                'points'         => $points,
                'amount_eur'     => $amountEur,
                'reason'         => $reason,
                'origin_type'    => 'conversion',
                'request_status' => 'pending',
                'request_date'   => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s']
        );

        $id = (int) $this->wpdb->insert_id;
        $this->wpdb->update(
            $this->table,
            ['origin_id' => $id],
            ['id' => $id],
            ['%d'],
            ['%d']
        );

        return $id;
    }

    /**
     * Update status and related dates for a conversion request.
     */
    public function updateRequestStatus(int $id, string $status, array $dates = []): void
    {
        $data = ['request_status' => $status];
        $format = ['%s'];

        if (isset($dates['settlement_date'])) {
            $data['settlement_date'] = $dates['settlement_date'];
            $format[] = '%s';
        }

        if (isset($dates['cancelled_date'])) {
            $data['cancelled_date'] = $dates['cancelled_date'];
            $format[] = '%s';
        }

        if (isset($dates['cancellation_reason'])) {
            $data['cancellation_reason'] = $dates['cancellation_reason'];
            $format[] = '%s';
        }

        $this->wpdb->update($this->table, $data, ['id' => $id], $format, ['%d']);
    }

    /**
     * Retrieve conversion requests.
     *
     * @param int|null    $userId Optional user filter.
     * @param string|null $status Optional request status filter.
     *
     * @return array[]
     */
    public function getConversionRequests(
        ?int $userId = null,
        ?string $status = null,
        ?int $limit = null,
        int $offset = 0
    ): array {
        $where  = "origin_type = 'conversion'";
        $params = [];

        if ($userId !== null) {
            $where   .= ' AND user_id = %d';
            $params[] = $userId;
        }

        if ($status !== null) {
            $where   .= ' AND request_status = %s';
            $params[] = $status;
        }

        $sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY request_date DESC";

        if ($limit !== null) {
            $sql     .= ' LIMIT %d OFFSET %d';
            $params[] = $limit;
            $params[] = $offset;
        }

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Retrieve a single conversion request by ID.
     */
    public function getRequestById(int $id): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d AND origin_type = 'conversion'",
            $id
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return $row ?: null;
    }

    /**
     * Sum of all points used by players excluding conversion requests.
     */
    public function getTotalPointsUsed(): int
    {
        $sql = $this->wpdb->prepare(
            "SELECT COALESCE(SUM(-points), 0) FROM {$this->table} WHERE points < 0 AND origin_type <> %s",
            'conversion'
        );

        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Sum of all points currently held by users.
     */
    public function getTotalPointsInCirculation(): int
    {
        $subquery = "SELECT MAX(id) AS id FROM {$this->table} GROUP BY user_id";
        $sql      = "SELECT COALESCE(SUM(balance), 0) FROM {$this->table} WHERE id IN ($subquery)";

        return (int) $this->wpdb->get_var($sql);
    }
}
