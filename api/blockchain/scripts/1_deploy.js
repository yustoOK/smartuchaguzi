const hre = require("hardhat");

async function main() {
  const [deployer] = await hre.ethers.getSigners();
  console.log("Deploying contracts with the account:", deployer.address);

  const VoteContract = await hre.ethers.getContractFactory("VoteContract");
  const voteContract = await VoteContract.deploy();

  await voteContract.deployed();
  console.log("VoteContract deployed to:", voteContract.address);
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});