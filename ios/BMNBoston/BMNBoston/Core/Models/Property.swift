import Foundation

struct Property: Codable, Identifiable, Sendable {
    let id: Int
    let listingId: String
    let address: String
    let unitNumber: String?
    let city: String
    let state: String
    let zip: String
    let price: Int
    let beds: Double?
    let baths: Double?
    let sqft: Int?
    let lotSize: Double?
    let yearBuilt: Int?
    let propertyType: String?
    let propertySubType: String?
    let status: String
    let daysOnMarket: Int?
    let latitude: Double?
    let longitude: Double?
    let photoUrl: String?
    let photoCount: Int?
    let listDate: Date?
    let modificationTimestamp: Date?

    enum CodingKeys: String, CodingKey {
        case id
        case listingId = "listing_id"
        case address
        case unitNumber = "unit_number"
        case city, state, zip, price, beds, baths, sqft
        case lotSize = "lot_size"
        case yearBuilt = "year_built"
        case propertyType = "property_type"
        case propertySubType = "property_sub_type"
        case status
        case daysOnMarket = "days_on_market"
        case latitude, longitude
        case photoUrl = "photo_url"
        case photoCount = "photo_count"
        case listDate = "list_date"
        case modificationTimestamp = "modification_timestamp"
    }
}
