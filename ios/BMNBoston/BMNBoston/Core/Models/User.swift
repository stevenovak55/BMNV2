import Foundation

struct User: Codable, Identifiable, Sendable {
    let id: Int
    let email: String
    let displayName: String
    let role: String
    let agentId: Int?
    let createdAt: Date?

    enum CodingKeys: String, CodingKey {
        case id, email, role
        case displayName = "display_name"
        case agentId = "agent_id"
        case createdAt = "created_at"
    }
}
