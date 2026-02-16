import SwiftUI

struct AppointmentsView: View {
    var body: some View {
        NavigationStack {
            Text("Appointments")
                .navigationTitle("Appointments")
        }
    }
}

#Preview {
    AppointmentsView()
}
