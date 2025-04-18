const hre = require("hardhat");

async function main() {
    const [deployer] = await hre.ethers.getSigners();
    console.log("Deploying contracts with:", deployer.address);

    const ElectionContract = await hre.ethers.getContractFactory("ElectionContract");
    const electionContract = await ElectionContract.deploy();
    await electionContract.deployed();
    console.log("ElectionContract deployed to:", electionContract.address);

    const VoteContract = await hre.ethers.getContractFactory("VoteContract");
    const voteContract = await VoteContract.deploy(electionContract.address);
    await voteContract.deployed();
    console.log("VoteContract deployed to:", voteContract.address);
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});