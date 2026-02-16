<?php

declare(strict_types=1);

namespace BMN\Extractor\Repository;

use BMN\Platform\Database\Repository;

class AgentRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_agents';
    }

    public function findByMlsId(string $agentMlsId): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE agent_mls_id = %s LIMIT 1",
                $agentMlsId
            )
        );
        return $result ?: null;
    }

    /**
     * Upsert agent by agent_mls_id.
     */
    public function upsert(array $data): void
    {
        $existing = $this->findByMlsId($data['agent_mls_id']);
        if ($existing) {
            $this->update($existing->id, $data);
        } else {
            $this->create($data);
        }
    }
}
