import SwiftUI

@Observable
final class AppState {
    var isAuthenticated = false
    var currentUser: User?
    var selectedTab: Tab = .search

    enum Tab: Hashable {
        case search
        case favorites
        case savedSearches
        case notifications
        case profile
    }
}
