<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

class FlowBuilderService
{
    private Database $db;
    private Logger $logger;

    public function __construct(Database $db, Logger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    public function getFlowTree(): array
    {
        $nodes = $this->db->fetchAll(
            "SELECT * FROM flow_nodes ORDER BY position_order ASC, id ASC",
            []
        ) ?? [];

        $options = $this->db->fetchAll(
            "SELECT * FROM flow_options ORDER BY node_id ASC, position_order ASC",
            []
        ) ?? [];

        $optionsByNode = [];
        foreach ($options as $opt) {
            $optionsByNode[(int)$opt['node_id']][] = $opt;
        }

        foreach ($nodes as &$node) {
            $node['options'] = $optionsByNode[(int)$node['id']] ?? [];
            $node['trigger_keywords'] = json_decode($node['trigger_keywords'], true) ?? [];
        }
        unset($node);

        return $nodes;
    }

    public function saveNode(array $data): int
    {
        $this->validateNodeData($data);

        $nextNodeId = isset($data['next_node_id']) ? (int)$data['next_node_id'] : null;

        if (isset($data['id']) && $nextNodeId) {
            if ($this->detectCycle((int)$data['id'], $nextNodeId)) {
                throw new \InvalidArgumentException('El nodo destino crearía un ciclo infinito en el flujo.');
            }
        }

        $nodeFields = [
            'name'              => $data['name'],
            'trigger_keywords'  => json_encode($data['trigger_keywords'] ?? []),
            'message_text'      => $data['message_text'],
            'next_node_id'      => $nextNodeId,
            'is_root'           => (int)($data['is_root'] ?? false),
            'requires_calendar' => (int)($data['requires_calendar'] ?? false),
            'match_any_input'   => (int)($data['match_any_input'] ?? false),
            'position_order'    => (int)($data['position_order'] ?? 0),
            'is_active'         => (int)($data['is_active'] ?? true),
        ];

        if (isset($data['id']) && $data['id']) {
            $nodeId = (int)$data['id'];
            $this->db->update('flow_nodes', $nodeFields, 'id = :id', [':id' => $nodeId]);
        } else {
            $nodeId = $this->db->insert('flow_nodes', $nodeFields);
        }

        $this->db->query("DELETE FROM flow_options WHERE node_id = :node_id", [':node_id' => $nodeId]);

        foreach ($data['options'] ?? [] as $i => $opt) {
            $optNextNodeId = isset($opt['next_node_id']) ? (int)$opt['next_node_id'] : null;

            if ($optNextNodeId && $this->detectCycle($nodeId, $optNextNodeId)) {
                throw new \InvalidArgumentException(
                    "La opción \"{$opt['option_text']}\" crearía un ciclo infinito."
                );
            }

            $this->db->insert('flow_options', [
                'node_id'         => $nodeId,
                'option_text'     => $opt['option_text'],
                'option_keywords' => json_encode($opt['option_keywords'] ?? []),
                'next_node_id'    => $optNextNodeId,
                'position_order'  => (int)($opt['position_order'] ?? $i),
            ]);
        }

        $this->logger->info('FlowBuilder: node saved', ['node_id' => $nodeId]);
        return $nodeId;
    }

    public function deleteNode(int $id): void
    {
        $this->db->query("UPDATE flow_nodes  SET next_node_id = NULL WHERE next_node_id = :id", [':id' => $id]);
        $this->db->query("UPDATE flow_options SET next_node_id = NULL WHERE next_node_id = :id", [':id' => $id]);
        $this->db->query("DELETE FROM flow_nodes WHERE id = :id", [':id' => $id]);
        $this->logger->info('FlowBuilder: node deleted', ['node_id' => $id]);
    }

    public function detectCycle(int $startId, int $targetId): bool
    {
        if ($startId === $targetId) {
            return true;
        }

        $visited = [];
        $stack   = [$targetId];

        while (!empty($stack)) {
            $current = array_pop($stack);

            if ($current === $startId) {
                return true;
            }

            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            $nodeRow = $this->db->fetchOne(
                "SELECT next_node_id FROM flow_nodes WHERE id = :id",
                [':id' => $current]
            );
            if ($nodeRow && $nodeRow['next_node_id']) {
                $stack[] = (int)$nodeRow['next_node_id'];
            }

            $optRows = $this->db->fetchAll(
                "SELECT next_node_id FROM flow_options WHERE node_id = :id AND next_node_id IS NOT NULL",
                [':id' => $current]
            );
            foreach ($optRows ?? [] as $opt) {
                $stack[] = (int)$opt['next_node_id'];
            }
        }

        return false;
    }

    public function exportToJson(): string
    {
        return json_encode([
            'version'    => '1.0',
            'exported_at' => date('c'),
            'nodes'      => $this->getFlowTree(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function importFromJson(string $json): array
    {
        $data = json_decode($json, true);
        if (!isset($data['nodes']) || !is_array($data['nodes'])) {
            throw new \InvalidArgumentException('JSON de importación inválido: falta el array "nodes".');
        }

        $this->db->query("DELETE FROM flow_options", []);
        $this->db->query("DELETE FROM flow_nodes",   []);

        $oldToNew = [];

        foreach ($data['nodes'] as $node) {
            $oldId = (int)$node['id'];

            $newId = $this->db->insert('flow_nodes', [
                'name'              => $node['name'],
                'trigger_keywords'  => json_encode($node['trigger_keywords'] ?? []),
                'message_text'      => $node['message_text'],
                'next_node_id'      => null,
                'is_root'           => (int)($node['is_root'] ?? false),
                'requires_calendar' => (int)($node['requires_calendar'] ?? false),
                'match_any_input'   => (int)($node['match_any_input'] ?? false),
                'position_order'    => (int)($node['position_order'] ?? 0),
                'is_active'         => (int)($node['is_active'] ?? true),
            ]);

            $oldToNew[$oldId] = $newId;
        }

        foreach ($data['nodes'] as $node) {
            $oldId = (int)$node['id'];
            $newId = $oldToNew[$oldId];

            if (!empty($node['next_node_id'])) {
                $newNext = $oldToNew[(int)$node['next_node_id']] ?? null;
                if ($newNext) {
                    $this->db->query(
                        "UPDATE flow_nodes SET next_node_id = :next WHERE id = :id",
                        [':next' => $newNext, ':id' => $newId]
                    );
                }
            }

            foreach ($node['options'] ?? [] as $i => $opt) {
                $optNext = !empty($opt['next_node_id']) ? ($oldToNew[(int)$opt['next_node_id']] ?? null) : null;
                $this->db->insert('flow_options', [
                    'node_id'         => $newId,
                    'option_text'     => $opt['option_text'],
                    'option_keywords' => json_encode($opt['option_keywords'] ?? []),
                    'next_node_id'    => $optNext,
                    'position_order'  => (int)($opt['position_order'] ?? $i),
                ]);
            }
        }

        $imported = count($data['nodes']);
        $this->logger->info('FlowBuilder: flow imported', ['nodes' => $imported]);

        return ['imported_nodes' => $imported, 'id_map' => $oldToNew];
    }

    private function validateNodeData(array $data): void
    {
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('El campo "name" es obligatorio.');
        }
        if (!isset($data['message_text']) || $data['message_text'] === '') {
            throw new \InvalidArgumentException('El campo "message_text" es obligatorio.');
        }
    }
}
