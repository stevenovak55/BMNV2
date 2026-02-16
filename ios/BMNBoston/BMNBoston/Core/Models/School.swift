import Foundation

struct School: Codable, Identifiable, Sendable {
    let id: Int
    let name: String
    let level: String
    let grade: String?
    let city: String
    let district: String?
    let compositeScore: Double?
    let latitude: Double?
    let longitude: Double?

    enum CodingKeys: String, CodingKey {
        case id, name, level, grade, city, district
        case compositeScore = "composite_score"
        case latitude, longitude
    }
}
