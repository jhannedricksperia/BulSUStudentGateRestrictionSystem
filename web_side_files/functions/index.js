// index.js
const { onValueCreated } = require("firebase-functions/v2/database");
const { initializeApp } = require("firebase-admin/app");
const { getDatabase } = require("firebase-admin/database");
const { getMessaging } = require("firebase-admin/messaging");
const functions = require("firebase-functions");

// Initialize Firebase Admin
initializeApp();

// Cloud Function: Trigger when a new notification is added to /Notifications
exports.sendNotification = onValueCreated("/Notifications/{notifId}", async (event) => {
  try {
    const snapshot = event.data;
    if (!snapshot) {
      console.log("⚠️ Snapshot is empty.");
      return null;
    }

    const notif = snapshot.val();
    if (!notif || !notif.studentNumber) {
      console.log("⚠️ Notification is missing studentNumber or content.");
      return null;
    }

    const db = getDatabase();
    const tokenSnap = await db.ref(`/Student/${notif.studentNumber}/fcmToken`).once("value");
    const token = tokenSnap.exists() ? tokenSnap.val() : null;

    if (!token) {
      console.log(`⚠️ No FCM token found for student: ${notif.studentNumber}`);
      return null;
    }

    const message = {
  notification: {
    title: `Gate ${notif.status || "Update"}`,
    body: notif.content || `${notif.studentNumber} ${notif.status} at ${notif.gate || "Unknown Gate"}`,
  },
  data: {
    content: notif.content || `${notif.status} at ${notif.gate || "Unknown Gate"}`,
    studentNumber: notif.studentNumber.toString(),
    status: notif.status || "",
    gate: notif.gate || ""
  },
  token: token,
  android: {
    priority: "high",
    notification: {
      channelId: "gate_entry_channel",
      sound: "default"
    }
  }
};

    await getMessaging().send(message);
    console.log(`✅ Notification sent to ${notif.studentNumber}`);

  } catch (error) {
    console.error("❌ Error sending notification:", error);
  }

  return null;
});

