// server.js
import express from "express";
import { MongoClient, ObjectId } from "mongodb";
import cors from "cors";

const app = express();
app.use(cors());
app.use(express.json());

// Koneksi MongoDB
const mongoURL = "mongodb://doitAdmin:doitIntegra.sys@192.168.7.5:27017";
const dbName = "api_sum";
const client = new MongoClient(mongoURL);

let db, collection;

async function connectDB() {
  try {
    await client.connect();
    db = client.db(dbName);
    collection = db.collection("picking_process");
    console.log(`✅ Terhubung ke MongoDB: ${dbName}`);
  } catch (err) {
    console.error("❌ Gagal konek MongoDB:", err);
  }
}
connectDB();

// ===== ROUTES =====

// GET semua data
app.get("/api/picking-process", async (req, res) => {
  try {
    const data = await collection.find({}).toArray();
    res.json(data);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// POST (tambah data)
app.post("/api/picking-process", async (req, res) => {
  try {
    const result = await collection.insertOne(req.body);
    res.json(result);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// PUT (edit data)
app.put("/api/picking-process/:id", async (req, res) => {
  try {
    const id = req.params.id;
    const result = await collection.updateOne(
      { _id: new ObjectId(id) },
      { $set: req.body }
    );
    res.json(result);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// DELETE data
app.delete("/api/picking-process/:id", async (req, res) => {
  try {
    const id = req.params.id;
    const result = await collection.deleteOne({ _id: new ObjectId(id) });
    res.json(result);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Jalankan server
const PORT = 3000;
app.listen(PORT, () => console.log(`🚀 Server berjalan di http://localhost:${PORT}`));
