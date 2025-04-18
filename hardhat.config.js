require("@nomicfoundation/hardhat-toolbox");
require("dotenv").config();

/** @type import('hardhat/config').HardhatUserConfig */

module.exports = {
    solidity: "0.8.20",
    networks: {
        sepolia: {
            url: process.env.SEPOLIA_RPC_URL || "https://eth-sepolia.g.alchemy.com/v2/CQ6TTcM9MPTHbeid-JggeOzMWZHhsx8d",
            accounts: [process.env.PRIVATE_KEY]
        }
    },
    paths: {
        sources: "./api/blockchain/contracts",
        artifacts: "./api/blockchain/artifacts"
    }
};
